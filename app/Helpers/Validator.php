<?php

namespace App\Helpers;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = isset($data[$field]) ? trim($data[$field]) : null;
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->errors[$field][] = "Field " . ucfirst(str_replace('_', ' ', $field)) . " wajib diisi.";
                } elseif ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "Format email pada " . ucfirst(str_replace('_', ' ', $field)) . " tidak valid.";
                } elseif ($rule === 'phone_id' && !empty($value)) {
                    $cleanPhone = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
                        $this->errors[$field][] = "Nomor telepon harus berupa 10 hingga 15 digit angka.";
                    }
                } elseif ($rule === 'rt_rw' && !empty($value)) {
                    if (!ctype_digit($value) || strlen($value) > 3) {
                        $this->errors[$field][] = "Field " . strtoupper($field) . " harus berupa angka (1-3 digit).";
                    }
                } elseif ($rule === 'latitude' && !empty($value)) {
                    if (!is_numeric($value) || $value < -90 || $value > 90) {
                        $this->errors[$field][] = "Nilai Latitude harus antara -90 dan 90.";
                    }
                } elseif ($rule === 'longitude' && !empty($value)) {
                    if (!is_numeric($value) || $value < -180 || $value > 180) {
                        $this->errors[$field][] = "Nilai Longitude harus antara -180 dan 180.";
                    }
                } elseif ($rule === 'numeric_positive' && !empty($value)) {
                    if (!is_numeric($value) || $value <= 0) {
                        $this->errors[$field][] = "Field " . ucfirst(str_replace('_', ' ', $field)) . " harus angka lebih besar dari 0.";
                    }
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int) str_replace('min:', '', $rule);
                    if (!empty($value) && strlen($value) < $min) {
                        $this->errors[$field][] = "Field " . ucfirst(str_replace('_', ' ', $field)) . " minimal {$min} karakter.";
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (int) str_replace('max:', '', $rule);
                    if (!empty($value) && strlen($value) > $max) {
                        $this->errors[$field][] = "Field " . ucfirst(str_replace('_', ' ', $field)) . " maksimal {$max} karakter.";
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstErrors(): array
    {
        $firstErrors = [];
        foreach ($this->errors as $field => $messages) {
            $firstErrors[$field] = $messages[0] ?? '';
        }
        return $firstErrors;
    }
}
