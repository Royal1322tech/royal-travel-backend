<?php

$tables = [
    "users",
    "rt_companies",
    "rt_company_memberships",
    "rt_roles",
    "rt_permissions",
    "rt_role_permissions",
    "rt_user_roles"
];

foreach ($tables as $table) {
    echo $table . ": " .
        implode(", ", \Illuminate\Support\Facades\Schema::getColumnListing($table)) .
        PHP_EOL;
}
