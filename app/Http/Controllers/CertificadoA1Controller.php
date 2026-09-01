<?php

namespace App\Http\Controllers;

use App\Models\CertificadoA1;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CertificadoA1Controller extends Controller
{
    private const COLUMNS = ['id', 'nome', 'criado_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('certificados_a1.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = CertificadoA1::withTrashed();
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
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
            ->map(fn (CertificadoA1 $certificado): array => [
                'id' => $certificado->id,
                'nome' => e($certificado->nome ?: '—'),
                'criado_em' => $certificado->criado_em?->format('d/m/Y H:i') ?? '—',
                'alterado_em' => $certificado->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('certificados_a1.partials.actions', [
                    'certificado' => $certificado,
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
        return view('certificados_a1.form', ['certificado' => new CertificadoA1()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request);unset($data['arquivo_certificado'],$data['senha_certificado']);$data=array_merge($data,$this->storeCertificate($request));
        $data['criado_por'] = $this->sessionUserId($request);
        $certificado = CertificadoA1::query()->create($data);

        return redirect()->route('certificados_a1.show', $certificado)
            ->with('status', 'Certificado A1 cadastrado com sucesso.');
    }

    public function show(CertificadoA1 $certificado): View
    {
        return view('certificados_a1.show', compact('certificado'));
    }

    public function edit(CertificadoA1 $certificado): View
    {
        return view('certificados_a1.form', compact('certificado'));
    }

    public function update(Request $request, CertificadoA1 $certificado): RedirectResponse
    {
        $data=$this->validated($request,true);unset($data['arquivo_certificado'],$data['senha_certificado']);$old=$certificado->arquivo;
        if($request->hasFile('arquivo_certificado'))$data=array_merge($data,$this->storeCertificate($request));
        elseif($request->filled('senha_certificado')){$metadata=$this->readCertificate($certificado->certificatePath(),(string)$request->input('senha_certificado'));$data=array_merge($data,$metadata,['senha'=>(string)$request->input('senha_certificado')]);}
        $certificado->update($data);
        if($request->hasFile('arquivo_certificado')&&filled($old)&&$old!==$certificado->arquivo)Storage::disk('local')->delete('certificados-a1/'.$old);

        return redirect()->route('certificados_a1.show', $certificado)
            ->with('status', 'Certificado A1 atualizado com sucesso.');
    }

    public function destroy(CertificadoA1 $certificado): RedirectResponse
    {
        $certificado->delete();

        return redirect()->route('certificados_a1.index')
            ->with('status', 'Certificado A1 excluído com sucesso.');
    }

    public function forceDestroy(int $certificado): RedirectResponse
    {
        $model=CertificadoA1::withTrashed()->findOrFail($certificado);if(filled($model->arquivo))Storage::disk('local')->delete('certificados-a1/'.$model->arquivo);$model->forceDelete();

        return redirect()->route('certificados_a1.index')
            ->with('status', 'Certificado A1 excluído definitivamente.');
    }

    private function validated(Request $request,bool $editing=false): array
    {
        return $request->validate([
            'nome' => ['nullable', 'string', 'max:50'],
            'arquivo_certificado'=>[$editing?'nullable':'required','file','max:5120','extensions:pfx,p12'],
            'senha_certificado'=>[$request->hasFile('arquivo_certificado')?'required':'nullable','string','max:255'],
        ]);
    }

    private function storeCertificate(Request $request): array
    {
        $file=$request->file('arquivo_certificado');$password=(string)$request->input('senha_certificado');$metadata=$this->readCertificate($file->getRealPath(),$password);
        $name=Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());$stored=Storage::disk('local')->putFileAs('certificados-a1',$file,$name);if(!$stored)throw ValidationException::withMessages(['arquivo_certificado'=>'Não foi possível armazenar o certificado em área privada.']);
        return array_merge($metadata,['arquivo'=>$name,'nome_arquivo_original'=>$file->getClientOriginalName(),'senha'=>$password]);
    }

    private function readCertificate(?string $path,string $password): array
    {
        if(!$path||!is_file($path))throw ValidationException::withMessages(['arquivo_certificado'=>'O arquivo atual do certificado não foi encontrado.']);
        $certificates=[];$valid=@openssl_pkcs12_read((string)file_get_contents($path),$certificates,$password);
        if(!$valid||empty($certificates['cert']))throw ValidationException::withMessages(['arquivo_certificado'=>'Não foi possível abrir o certificado. Verifique o arquivo e a senha informada.']);
        $details=openssl_x509_parse($certificates['cert'])?:[];$subject=(array)($details['subject']??[]);$holder=$subject['CN']??$subject['O']??null;
        return ['titular'=>$holder,'impressao_digital'=>openssl_x509_fingerprint($certificates['cert'],'sha256')?:null,'valido_de'=>isset($details['validFrom_time_t'])?date('Y-m-d H:i:s',$details['validFrom_time_t']):null,'valido_ate'=>isset($details['validTo_time_t'])?date('Y-m-d H:i:s',$details['validTo_time_t']):null];
    }

    private function sessionUserId(Request $request): ?int
    {
        $id = (int) $request->session()->get('gi_context.usuario.id', 0);
        return $id > 0 ? $id : null;
    }
}
