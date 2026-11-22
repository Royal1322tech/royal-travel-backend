<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\B2B\Customers;
use App\Http\Controllers\Controller;
use App\Services\Bookings\CustomerDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
final class CustomerController extends Controller {
    private function companyId(Request $request): string {
        abort_unless($request->user(), 401);
        $id = $request->attributes->get('authenticated_company_id');
        abort_unless(is_string($id) && Str::isUuid($id), 403, 'Company context required.');
        return strtolower($id);
    }
    public function index(Request $request, CustomerDirectoryService $directory): JsonResponse {
        $company = $this->companyId($request);
        $data = $request->validate(['q'=>['sometimes','string','max:100']]);
        return response()->json($directory->search($company, $data['q'] ?? ''))->header('Cache-Control','no-store');
    }
    public function store(Request $request, CustomerDirectoryService $directory): JsonResponse {
        $company = $this->companyId($request);
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
