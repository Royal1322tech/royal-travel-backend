<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Bookings\CustomerDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
final class CustomerController extends Controller {
    private function validatedCompany(Request $request): ?string {
        $data = $request->validate(['company_id'=>['nullable','uuid',Rule::exists('rt_companies','id')]]);
        return isset($data['company_id']) ? strtolower($data['company_id']) : null;
    }
    public function index(Request $request, CustomerDirectoryService $directory): JsonResponse {
        $company = $this->validatedCompany($request);
        $data = $request->validate(['q'=>['sometimes','string','max:100']]);
        return response()->json($directory->search($company,$data['q'] ?? ''))->header('Cache-Control','no-store');
    }
    public function store(Request $request, CustomerDirectoryService $directory): JsonResponse {
        $company = $this->validatedCompany($request);
        $data = $request->validate([
            'first_name'=>['required','string','max:100'],
            'last_name'=>['required','string','max:100'],
            'email'=>['nullable','email','max:254','required_without:phone'],
            'phone'=>['nullable','string','max:35','required_without:email'],
        ]);
        $customer = $directory->create($company,(int)$request->user()->getAuthIdentifier(),$data);
        return response()->json(['data'=>$customer],201)->header('Cache-Control','no-store');
    }
}
