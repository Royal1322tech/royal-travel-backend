<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\B2B\Customers;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Customer;
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
        $page = $directory->search($company, $data['q'] ?? '')->through(fn ($customer) => self::present($customer));
        return response()->json($page)->header('Cache-Control','no-store');
    }
    /** One of the company's customers; another company's customer is simply not found. */
    public function show(Request $request, string $customer, CustomerDirectoryService $directory): JsonResponse {
        $company = $this->companyId($request);
        abort_unless(Str::isUuid($customer), 404, 'Customer not found.');
        return response()->json(['data'=>self::present($directory->findForBooking(strtolower($customer), $company))])->header('Cache-Control','no-store');
    }
    private static function timestamp(mixed $value): ?string {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : ($value === null ? null : (string) $value);
    }
    /** The customer fields a company may see: no creator user ID or internal columns. */
    private static function present(Customer $customer): array {
        return [
            'id'=>$customer->id,
            'first_name'=>$customer->first_name,
            'last_name'=>$customer->last_name,
            'email'=>$customer->email,
            'phone'=>$customer->phone,
            'created_at'=>self::timestamp($customer->created_at),
            'updated_at'=>self::timestamp($customer->updated_at),
        ];
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
        return response()->json(['data'=>self::present($customer)],201)->header('Cache-Control','no-store');
    }
}
