<?php

$rules = (new \App\Http\Requests\Api\V1\B2C\Hotels\HotelSearchRequest)->rules();

foreach (["USD", "EGP", "SAR", "EUR"] as $currency) {
    $validator = \Illuminate\Support\Facades\Validator::make(
        ["currency" => $currency],
        ["currency" => $rules["currency"]]
    );

    echo $currency . ": " .
        ($validator->passes() ? "ACCEPTED" : "REJECTED") .
        PHP_EOL;
}
