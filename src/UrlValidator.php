<?php

namespace Hexlet\Code;

use Valitron\Validator;

class UrlValidator
{
    public function validateUrl(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'URL не должен быть пустым';
            return $errors;
        }

        $url = new Validator(['name' => $data['name']]);
        $url->rule('url', 'name');
        $url->rule('lengthMax', 'name', 255);

        if (!$url->validate()) {
            $errors['name'] = 'Некорректный URL';
        }

        return $errors;
    }
}
