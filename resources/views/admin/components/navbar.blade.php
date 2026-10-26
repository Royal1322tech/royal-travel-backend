
<form method="POST" action="{{ route('admin.logout') }}">
    @csrf

    <button
        type="submit"
        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-red-600"
    >
        Logout
    </button>
</form>
