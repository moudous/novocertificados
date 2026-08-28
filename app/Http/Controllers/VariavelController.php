<?php

namespace App\Http\Controllers;

use App\Models\Variavel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VariavelController extends Controller
{
    private const COLUMNS = ['id', 'nome', 'tipo', 'texto', 'ativo', 'criado_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('variaveis.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Variavel::withTrashed();
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('tipo', 'like', "%{$search}%")
                    ->orWhere('texto', 'like', "%{$search}%")
                    ->orWhere('imagem', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (Variavel $variavel): array => [
                'id' => $variavel->id,
                'nome' => e($variavel->nome ?: '—'),
                'tipo' => e(ucfirst((string) $variavel->tipo) ?: '—'),
                'conteudo' => $variavel->tipo === 'imagem'
                    ? ($variavel->imageExists()
                        ? '<img src="'.e($variavel->imageUrl()).'" alt="Miniatura" class="rounded border" style="width:72px;height:48px;object-fit:contain">'
                        : e($variavel->imagem ?: '—'))
                    : e(Str::limit($variavel->texto ?: '—', 80)),
                'ativo' => $variavel->trashed()
                    ? '<span class="badge text-bg-danger">Excluída</span>'
                    : ($variavel->ativo
                        ? '<span class="badge text-bg-success">Ativa</span>'
                        : '<span class="badge text-bg-secondary">Inativa</span>'),
                'criado_em' => $variavel->criado_em?->format('d/m/Y H:i') ?? '—',
                'alterado_em' => $variavel->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('variaveis.partials.actions', [
                    'variavel' => $variavel,
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

    public function create(): View
    {
        return view('variaveis.form', ['variavel' => new Variavel()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['remover_imagem']);

        if ($data['tipo'] === 'imagem') {
            $data['texto'] = null;
            $data['alinhamento'] = null;
            $data['cor'] = null;
        } else {
            unset($data['imagem']);
        }

        if ($data['tipo'] === 'imagem' && $request->hasFile('imagem')) {
            $data['imagem'] = $this->storeImage($request);
        }

        $variavel = Variavel::query()->create($data);

        return redirect()->route('variaveis.show', $variavel)->with('status', 'Variável cadastrada com sucesso.');
    }

    public function show(Variavel $variavel): View
    {
        return view('variaveis.show', compact('variavel'));
    }

    public function edit(Variavel $variavel): View
    {
        return view('variaveis.form', compact('variavel'));
    }

    public function update(Request $request, Variavel $variavel): RedirectResponse
    {
        $data = $this->validated($request);
        $oldImage = $variavel->imagem;

        if ($data['tipo'] === 'imagem') {
            $data['texto'] = null;
            $data['alinhamento'] = null;
            $data['cor'] = null;
        }

        if ($data['tipo'] === 'imagem' && $request->hasFile('imagem')) {
            $data['imagem'] = $this->storeImage($request);
            $this->removeImage($oldImage);
        } elseif ($request->boolean('remover_imagem') || $data['tipo'] !== 'imagem') {
            $this->removeImage($oldImage);
            $data['imagem'] = null;
        }

        unset($data['remover_imagem']);
        $variavel->update($data);

        return redirect()->route('variaveis.show', $variavel)->with('status', 'Variável atualizada com sucesso.');
    }

    public function toggleStatus(Variavel $variavel): RedirectResponse
    {
        $variavel->update(['ativo' => ! $variavel->ativo]);

        return redirect()->route('variaveis.index')->with('status', 'Status da variável atualizado com sucesso.');
    }

    public function destroy(Variavel $variavel): RedirectResponse
    {
        $variavel->delete();

        return redirect()->route('variaveis.index')->with('status', 'Variável excluída com sucesso.');
    }

    public function forceDestroy(int $variavel): RedirectResponse
    {
        $model = Variavel::withTrashed()->findOrFail($variavel);
        $this->removeImage($model->imagem);
        $model->forceDelete();

        return redirect()->route('variaveis.index')->with('status', 'Variável excluída definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:20'],
            'tipo' => ['required', Rule::in(['imagem', 'texto'])],
            'imagem' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:10240'],
            'remover_imagem' => ['nullable', 'boolean'],
            'texto' => ['nullable', 'string'],
            'ativo' => ['required', 'boolean'],
            'pos_x' => ['nullable', 'integer'],
            'pox_y' => ['nullable', 'integer'],
            'altura' => ['nullable', 'integer', 'min:0'],
            'largura' => ['nullable', 'integer', 'min:0'],
            'alinhamento' => ['nullable', Rule::in(['esquerda', 'direita', 'centralizado', 'justificado'])],
            'cor' => ['nullable', 'string', 'max:15'],
            'centro_x' => ['nullable', 'integer'],
            'centro_y' => ['nullable', 'integer'],
        ]);
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('imagem');
        $filename = hash('sha1', Str::uuid()->toString()).'.'.strtolower($file->getClientOriginalExtension());
        $directory = public_path('certificado/imagem_fundo');
        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        return $filename;
    }

    private function removeImage(?string $filename): void
    {
        if (filled($filename) && basename($filename) === $filename) {
            File::delete(public_path('certificado/imagem_fundo/'.$filename));
        }
    }
}
