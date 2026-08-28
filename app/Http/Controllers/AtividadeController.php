<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AtividadeController extends Controller
{
    private const COLUMNS = ['id', 'nome', 'eventoId', 'periodos', 'ativo', 'criado_em', 'atualizado_em'];

    public function index(Request $request): View
    {
        return view('atividades.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Atividade::withTrashed()->with('evento:id,nome');
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('periodos', 'like', "%{$search}%")
                    ->orWhere('descricao_old', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('evento', fn (Builder $evento): Builder => $evento->where('nome', 'like', "%{$search}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();
        $columnIndex = (int) $request->input('order.0.column', 0);
        $column = self::COLUMNS[$columnIndex] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (Atividade $atividade): array => [
                'id' => $atividade->id,
                'nome' => e($atividade->nome ?: '—'),
                'evento' => e($atividade->evento?->nome ?: '—'),
                'periodos' => e($atividade->periodos ?: '—'),
                'ativo' => $atividade->trashed()
                    ? '<span class="badge text-bg-danger">Excluída</span>'
                    : ($atividade->ativo
                        ? '<span class="badge text-bg-success">Ativa</span>'
                        : '<span class="badge text-bg-secondary">Inativa</span>'),
                'criado_em' => $atividade->criado_em?->format('d/m/Y H:i') ?? '—',
                'atualizado_em' => $atividade->atualizado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('atividades.partials.actions', [
                    'atividade' => $atividade,
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

    public function eventos(Request $request): JsonResponse
    {
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(
            in_array('atividades.criar', $permissions, true) || in_array('atividades.editar', $permissions, true),
            403,
        );

        $search = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $events = Evento::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where('nome', 'like', "%{$search}%"))
            ->orderBy('nome')->paginate(20, ['id', 'nome'], 'page', $page);

        return response()->json([
            'results' => collect($events->items())->map(fn (Evento $evento): array => [
                'id' => $evento->id,
                'text' => $evento->nome ?: "Evento #{$evento->id}",
            ])->values(),
            'pagination' => ['more' => $events->hasMorePages()],
        ]);
    }

    public function create(): View
    {
        return view('atividades.form', ['atividade' => new Atividade()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('imagemFundo')) {
            $data['imagemFundo'] = $this->storeBackground($request);
        }
        unset($data['remover_imagem']);
        $atividade = Atividade::query()->create($data);

        return redirect()->route('atividades.show', $atividade)->with('status', 'Atividade cadastrada com sucesso.');
    }

    public function show(Atividade $atividade): View
    {
        $atividade->load('evento');

        return view('atividades.show', compact('atividade'));
    }

    public function edit(Atividade $atividade): View
    {
        $atividade->load('evento');

        return view('atividades.form', compact('atividade'));
    }

    public function update(Request $request, Atividade $atividade): RedirectResponse
    {
        $data = $this->validated($request);
        $oldBackground = $atividade->imagemFundo;

        if ($request->hasFile('imagemFundo')) {
            $data['imagemFundo'] = $this->storeBackground($request);
            $this->removeBackground($oldBackground);
        } elseif ($request->boolean('remover_imagem')) {
            $this->removeBackground($oldBackground);
            $data['imagemFundo'] = null;
        }

        unset($data['remover_imagem']);
        $atividade->update($data);

        return redirect()->route('atividades.show', $atividade)->with('status', 'Atividade atualizada com sucesso.');
    }

    public function destroy(Atividade $atividade): RedirectResponse
    {
        $atividade->delete();

        return redirect()->route('atividades.index')->with('status', 'Atividade excluída com sucesso.');
    }

    public function restore(int $atividade): RedirectResponse
    {
        Atividade::withTrashed()->findOrFail($atividade)->restore();

        return redirect()->route('atividades.index')->with('status', 'Atividade restaurada com sucesso.');
    }

    public function forceDestroy(int $atividade): RedirectResponse
    {
        $model = Atividade::withTrashed()->findOrFail($atividade);
        $this->removeBackground($model->imagemFundo);
        $model->forceDelete();

        return redirect()->route('atividades.index')->with('status', 'Atividade excluída definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'eventoId' => ['nullable', 'integer', Rule::exists('eventos', 'id')->whereNull('apagado_em')],
            'nome' => ['nullable', 'string', 'max:200'],
            'descricao_old' => ['nullable', 'string'],
            'periodos' => ['nullable', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
            'imagemFundo' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
            'remover_imagem' => ['nullable', 'boolean'],
            'template' => ['nullable', 'string'],
        ]);
    }

    private function storeBackground(Request $request): string
    {
        $file = $request->file('imagemFundo');
        $filename = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $directory = public_path('certificado/imagem_fundo');
        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        return $filename;
    }

    private function removeBackground(?string $filename): void
    {
        if (blank($filename)) {
            return;
        }

        $safeName = basename($filename);
        if ($safeName === $filename) {
            File::delete(public_path('certificado/imagem_fundo/'.$safeName));
        }
    }
}
