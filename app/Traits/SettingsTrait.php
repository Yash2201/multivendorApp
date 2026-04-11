<?php

namespace App\Traits;

trait SettingsTrait
{
    use StorageTrait;

    public function setEnvironmentValue($envKey, $envValue): mixed
    {
        $envFile = app()->environmentFilePath();
        $contents = file_get_contents($envFile);
        if (preg_match('/[^a-zA-Z0-9]/', $envValue)) {
            $formattedValue = "\"{$envValue}\"";
        } else {
            $formattedValue = $envValue;
        }

        $pattern = "/^{$envKey}=.*$/m";
        $replacement = "{$envKey}={$formattedValue}";

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $replacement, $contents);
        } else {
            $contents .= PHP_EOL . $replacement . PHP_EOL;
        }
        $fp = fopen($envFile, 'w');
        fwrite($fp, $contents);
        fclose($fp);
        return $formattedValue;
    }

    public function getSettings($object, $type)
    {
        $config = null;
        foreach ($object as $setting) {
            if ($setting['type'] == $type) {
                $config = $this->storageDataProcessing($type, $setting);
            }
        }
        return $config;
    }

    private function storageDataProcessing($name, $value)
    {
        $arrayOfCompaniesValue = ['company_web_logo', 'company_mobile_logo', 'company_footer_logo', 'company_fav_icon', 'loader_gif', 'blog_feature_download_app_icon', 'blog_feature_download_app_background'];
        if (in_array($name, $arrayOfCompaniesValue)) {
            $imageData = json_decode($value->value, true) ?? ['image_name' => $value['value'], 'storage' => 'public'];
            $value['value'] = $this->storageLink('company', $imageData['image_name'], $imageData['storage']);
        }
        return $value;
    }
}
