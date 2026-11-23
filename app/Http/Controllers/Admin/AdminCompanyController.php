<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Modules\Identity\Models\Company;
use App\Modules\Identity\Services\AdminCompanyCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;





class AdminCompanyController extends Controller
{

public function index(Request $request): View
{
    $search = trim((string) $request->query('search', ''));
    $status = (string) $request->query('status', '');

    $allowedStatuses = [
        'pending',
        'approved',
        'suspended',
        'rejected',
    ];

    $companies = Company::query()
        ->when($search !== '', function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'ilike', '%' . $search . '%')
                    ->orWhere('display_name', 'ilike', '%' . $search . '%')
                    ->orWhere('registration_no', 'ilike', '%' . $search . '%');
            });
        })
        ->when(
            in_array($status, $allowedStatuses, true),
            fn ($query) => $query->where('status', $status)
        )
        ->orderByDesc('created_at')
        ->paginate(15)
        ->withQueryString();

    $pendingCount = Company::query()
        ->where('status', 'pending')
        ->count();

    return view('admin.companies.index', compact(
        'companies',
        'search',
        'status',
        'pendingCount'
    ));
}

    public function create(): View
    {
        return view('admin.companies.create');
    }

    public function store(
        StoreCompanyRequest $request,
        AdminCompanyCreationService $service
    ): RedirectResponse {
        $company = $service->createCompany(
            $request->user(),
            array_merge(
                $request->validated(),
                $request->allFiles()
            )
        );

        return redirect()
            ->route('admin.companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function show(Company $company): View
    {
        $contacts = DB::table('rt_company_contacts')
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'rt_company_contacts.user_id'
            )
            ->where('rt_company_contacts.company_id', $company->id)
            ->select(
                'rt_company_contacts.full_name',
                'rt_company_contacts.phone',
                'rt_company_contacts.nationality',
                'rt_company_contacts.contact_type',
                'users.email'
            )
            ->get();

        $members = DB::table('rt_company_memberships')
            ->join(
                'users',
                'users.id',
                '=',
                'rt_company_memberships.user_id'
            )
            ->where('rt_company_memberships.company_id', $company->id)
            ->select(
                'users.name',
                'users.email',
                'rt_company_memberships.role_code',
                'rt_company_memberships.status'
            )
            ->get();

        $finance = app(\App\Services\Finance\B2BFinanceService::class);
        $financeAccount = $finance->snapshot($company->id);
        $financeEntries = $finance->entries($company->id);

        return view('admin.companies.show', compact(
            'financeAccount', 'financeEntries', 'company',
            'contacts',
            'members'
        ));
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(
        Request $request,
        Company $company
    ): RedirectResponse {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'business_type' => [
                'required',
                Rule::in(['travel_agency', 'corporate']),
            ],
            'employee_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000000',
            ],
            'city' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:2000'],
        ]);

        $company->legal_name = $data['legal_name'];
        $company->display_name =
            $data['display_name'] ?: $data['legal_name'];
        $company->business_type = $data['business_type'];
        $company->employee_count = $data['employee_count'] ?? null;
        $company->city = $data['city'];
        $company->address = $data['address'];

        $company->save();

        return redirect()
            ->route('admin.companies.show', $company->id)
            ->with('success', 'Company updated successfully.');
    }









   
public function approve(
    Company $company,
    \App\Modules\Identity\Services\B2BRegistrationOtpService $otpService
): RedirectResponse {
    $ownerId = DB::transaction(function () use ($company): string {

        $locked = Company::query()
            ->whereKey($company->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== 'pending') {
            abort(409, 'Only pending companies can be approved.');
        }

        $ownerId = DB::table('rt_company_memberships')
            ->where('company_id', $locked->id)
            ->where('role_code', 'company_admin')
            ->where('status', 'pending')
            ->value('user_id');

        if (! $ownerId) {
            abort(409, 'Pending company owner not found.');
        }

        // Admin approves the company first.
        $locked->status = 'approved';
        $locked->save();

        DB::table('rt_company_memberships')
    ->where('company_id', $locked->id)
    ->where('user_id', $ownerId)
    ->where('role_code', 'company_admin')
    ->where('status', 'pending')
    ->update([
        'approved_by' => auth()->id(),
        'approved_at' => now(),
        'updated_at' => now(),
    ]);

        // Owner membership remains pending until OTP verification.
        // Other memberships also remain unchanged.

        return (string) $ownerId;
    });

    $owner = \App\Models\User::query()->findOrFail($ownerId);

    try {
        $otpService->send($owner, $company->id);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error(
            'Failed to send company approval OTP.',
            [
                'company_id' => $company->id,
                'user_id' => $ownerId,
                'exception' => $e->getMessage(),
            ]
        );

        return redirect()
            ->route('admin.companies.show', $company->id)
            ->with(
                'error',
                'Company approved, but OTP email could not be sent. Please retry email delivery.'
            );
    }

    return redirect()
        ->route('admin.companies.show', $company->id)
        ->with(
            'success',
            'Company approved successfully. Verification OTP sent to the owner.'
        );
}


public function deactivate(Company $company): RedirectResponse
{
    DB::transaction(function () use ($company): void {

        $locked = Company::query()
            ->whereKey($company->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== 'approved') {
            abort(409, 'Only approved companies can be deactivated.');
        }

        $locked->status = 'suspended';
        $locked->save();
    });

    return redirect()
        ->route('admin.companies.show', $company->id)
        ->with('success', 'Company deactivated successfully.');
}

public function activate(Company $company): RedirectResponse
{
    DB::transaction(function () use ($company): void {

        $locked = Company::query()
            ->whereKey($company->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== 'suspended') {
            abort(409, 'Only suspended companies can be reactivated.');
        }

        $locked->status = 'approved';
        $locked->save();
    });

    return redirect()
        ->route('admin.companies.show', $company->id)
        ->with('success', 'Company activated successfully.');
}
}
