<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeepLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|string',

            // ANDROID
            'android_package_name' => [
                'nullable',
                'string',
                'max:255',
                'required_with:android_sha256_fingerprint',
                'required_without_all:ios_bundle_id,ios_team_id',
            ],
            'android_sha256_fingerprint' => [
                'nullable',
                'string',
                'max:255',
                'required_with:android_package_name',
                'required_without_all:ios_bundle_id,ios_team_id',
            ],

            // IOS
            'ios_bundle_id' => [
                'nullable',
                'string',
                'max:255',
                'required_with:ios_team_id',
                'required_without_all:android_package_name,android_sha256_fingerprint',
            ],
            'ios_team_id' => [
                'nullable',
                'string',
                'max:255',
                'required_with:ios_bundle_id',
                'required_without_all:android_package_name,android_sha256_fingerprint',
            ],
            'playstore_redirect_url' => [
                'nullable',
                'string',
                'max:255',
                'required_with:android_sha256_fingerprint',
                'required_without_all:ios_bundle_id,ios_team_id',
            ],
            'app_store_redirect_url' => [
                'nullable',
                'string',
                'max:255',
                'required_with:ios_bundle_id',
                'required_without_all:android_package_name,android_sha256_fingerprint',
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'android_package_name.required_without_all' => translate('Either Android or iOS configuration is required.'),
            'ios_bundle_id.required_without_all' => translate('Either Android or iOS configuration is required.'),
            'android_package_name.required_with' => translate('Android Package Name and SHA256 Fingerprint must be provided together.'),
            'ios_bundle_id.required_with' => translate('iOS Bundle ID and Team ID must be provided together.'),
            'playstore_redirect_url.required_with' => translate('playstore_redirect_url and android_sha256_fingerprint must be provided together.'),
            'app_store_redirect_url.required_with' => translate('app_store_redirect_url and Team ID must be provided together.'),
        ];
    }

}
