<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use App\Models\RubricaParticipante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RubricaParticipanteController extends Controller
{
    private const COLUMNS = ['id', 'participante_id', 'id', 'ativo', 'criado_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('rubricas_participantes.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = RubricaParticipante::withTrashed()->with('participante');
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhereHas('participante', fn (Builder $participant): Builder => $participant
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (RubricaParticipante $rubrica): array => [
                'id' => $rubrica->id,
                'participante' => e($rubrica->participante?->nome ?: '—'),
                'rubrica' => $rubrica->signatureExists()
                    ? '<img src="'.e($rubrica->signatureUrl()).'" alt="Rubrica" class="rounded border bg-white" style="width:100px;height:48px;object-fit:contain">'
                    : e($rubrica->rubrica ?: '—'),
                'ativo' => $rubrica->trashed()
                    ? '<span class="badge text-bg-danger">Excluída</span>'
                    : ($rubrica->ativo
                        ? '<span class="badge text-bg-success">Ativa</span>'
                        : '<span class="badge text-bg-secondary">Inativa</span>'),
                'criado_em' => $rubrica->criado_em?->format('d/m/Y H:i') ?? '—',
                'alterado_em' => $rubrica->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('rubricas_participantes.partials.actions', [
                    'rubrica' => $rubrica,
                    'permissions' => $permissions,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function participantes(Request $request): JsonResponse
    {
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(
            in_array('rubricas_participantes.criar', $permissions, true)
                || in_array('rubricas_participantes.editar', $permissions, true),
            403,
        );

        $search = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $items = Participante::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $filter) use ($search): void {
                $filter->where('nome', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('nome')->paginate(20, ['id', 'nome', 'email'], 'page', $page);

        return response()->json([
            'results' => collect($items->items())->map(fn (Participante $item): array => [
                'id' => $item->id,
                'text' => $item->nome.($item->email ? ' · '.$item->email : ''),
            ])->values(),
            'pagination' => ['more' => $items->hasMorePages()],
        ]);
    }

    public function create(): View
    {
        return view('rubricas_participantes.form', ['rubrica' => new RubricaParticipante()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['remover_rubrica']);

        if ($request->hasFile('rubrica')) {
            $data['rubrica'] = $this->storeSignature($request);
        }

        $rubrica = RubricaParticipante::query()->create($data);

        return redirect()->route('rubricas_participantes.show', $rubrica)
            ->with('status', 'Rubrica cadastrada com sucesso.');
    }

    public function show(RubricaParticipante $rubrica): View
    {
        $rubrica->load('participante');

        return view('rubricas_participantes.show', compact('rubrica'));
    }

    public function edit(RubricaParticipante $rubrica): View
    {
        $rubrica->load('participante');

        return view('rubricas_participantes.form', compact('rubrica'));
    }

    public function update(Request $request, RubricaParticipante $rubrica): RedirectResponse
    {
        $data = $this->validated($request);
        $oldSignature = $rubrica->rubrica;

        if ($request->hasFile('rubrica')) {
            $data['rubrica'] = $this->storeSignature($request);
            $this->removeSignature($oldSignature);
        } elseif ($request->boolean('remover_rubrica')) {
            $this->removeSignature($oldSignature);
            $data['rubrica'] = null;
        }

        unset($data['remover_rubrica']);
        $rubrica->update($data);

        return redirect()->route('rubricas_participantes.show', $rubrica)
            ->with('status', 'Rubrica atualizada com sucesso.');
    }

    public function toggleStatus(RubricaParticipante $rubrica): RedirectResponse
    {
        $rubrica->update(['ativo' => ! $rubrica->ativo]);

        return redirect()->route('rubricas_participantes.index')->with('status', 'Status atualizado com sucesso.');
    }

    public function destroy(RubricaParticipante $rubrica): RedirectResponse
    {
        $rubrica->delete();

        return redirect()->route('rubricas_participantes.index')->with('status', 'Rubrica excluída com sucesso.');
    }

    public function forceDestroy(int $rubrica): RedirectResponse
    {
        $model = RubricaParticipante::withTrashed()->findOrFail($rubrica);
        $this->removeSignature($model->rubrica);
        $model->forceDelete();

        return redirect()->route('rubricas_participantes.index')->with('status', 'Rubrica excluída definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'rubrica' => ['nullable', 'image', 'mimes:png', 'max:2048'],
            'remover_rubrica' => ['nullable', 'boolean'],
            'participante_id' => ['nullable', 'integer', Rule::exists('participantes', 'id')->whereNull('excluido_em')],
            'ativo' => ['required', 'boolean'],
        ]);
    }

    private function storeSignature(Request $request): string
    {
        $file = $request->file('rubrica');
        $filename = hash('sha1', Str::uuid()->toString()).'.png';
        $directory = public_path('certificado/rubricas_participantes');
        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        return $filename;
    }

    private function removeSignature(?string $filename): void
    {
        if (filled($filename) && basename($filename) === $filename) {
            File::delete(public_path('certificado/rubricas_participantes/'.$filename));
        }
    }
}
