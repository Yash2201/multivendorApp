<?php

namespace App\Http\Requests\Shiprocket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for creating a Shiprocket pickup address.
 *
 * Field names and constraints intentionally mirror Shiprocket's
 * POST /settings/company/addpickup payload so the form maps 1:1 to the API.
 * The `pickup_nickname` (their `pickup_location`) is generated server-side and
 * is therefore not accepted from the request.
 */
class PickupAddressRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'phone' => ['required', 'regex:/^\d{10}$/'],
            'address' => 'required|string|max:120',
            'address_2' => 'nullable|string|max:120',
            'city' => 'required|string|max:60',
            'state' => 'required|string|max:60',
            'country' => 'required|string|max:60',
            'pin_code' => ['required', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => translate('the_pickup_contact_name_is_required'),
            'email.required' => translate('the_email_is_required'),
            'email.email' => translate('please_enter_a_valid_email'),
            'phone.required' => translate('the_phone_is_required'),
            'phone.regex' => translate('phone_must_be_a_valid_10_digit_mobile_number'),
            'address.required' => translate('the_address_is_required'),
            'city.required' => translate('the_city_is_required'),
            'state.required' => translate('the_state_is_required'),
            'country.required' => translate('the_country_is_required'),
            'pin_code.required' => translate('the_pincode_is_required'),
            'pin_code.regex' => translate('pincode_must_be_a_valid_6_digit_pincode'),
        ];
    }
}
