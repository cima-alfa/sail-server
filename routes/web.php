<?php

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

Route::redirect('/', 'https://laravel.com/docs', 302);

Route::get('/{name}', function (Request $request, $name) {
    $availableServices = [
        'mysql',
        'pgsql',
        'mariadb',
        'redis',
        'valkey',
        'memcached',
        'meilisearch',
        'typesense',
        'minio',
        'mailpit',
        'selenium',
        'soketi',
    ];

    $availableStarterKits = [
        'react',
        'vue',
        'livewire',
    ];

    $php = $request->query('php', '84');

    $with = array_unique(explode(',', $request->query('with', 'mysql,redis,meilisearch,mailpit,selenium')));

    $kit = $request->query('kit');

    try {
        Validator::validate(
            [
                'name' => $name,
                'php' => $php,
                'with' => $with,
                'kit' => $kit,
            ],
            [
                'name' => 'string|alpha_dash',
                'php' => ['string', Rule::in(['74', '80', '81', '82', '83', '84'])],
                'with' => 'array',
                'with.*' => [
                    'required',
                    'string',
                    count($with) === 1 && in_array('none', $with) ? Rule::in(['none']) : Rule::in($availableServices)
                ],
                'kit' => [$request->has('kit') ? 'required' : 'nullable', 'string', Rule::in($availableStarterKits)],
            ]
        );
    } catch (ValidationException $e) {
        $errors = Arr::undot($e->errors());

        if (array_key_exists('name', $errors)) {
            return response('Invalid site name. Please only use alpha-numeric characters, dashes, and underscores.', 400);
        }

        if (array_key_exists('php', $errors)) {
            return response('Invalid PHP version. Please specify a supported version (74, 80, 81, 82, 83, or 84).', 400);
        }

        if (array_key_exists('with', $errors)) {
            return response('Invalid service name. Please provide one or more of the supported services ('.implode(', ', $availableServices).') or "none".', 400);
        }
        
        if (array_key_exists('kit', $errors)) {
            return response('Invalid starter kit name. Please provide one of the supported starter kits ('.implode(', ', $availableStarterKits).').', 400);
        }
    }

    $options = '';

    if ($kit !== null) {
        $options .= "--$kit ";
    }

    if ($request->has('workos')) {
        $options .= '--workos ';
    }

    $services = implode(' ', $with);

    $with = implode(',', $with);

    $devcontainer = $request->has('devcontainer') ? '--devcontainer' : '';

    $script = str_replace(
        ['{{ php }}', '{{ name }}', '{{ options }}', '{{ with }}', '{{ devcontainer }}', '{{ services }}'],
        [$php, $name, $options, $with, $devcontainer, $services],
        file_get_contents(resource_path('scripts/php.sh'))
    );

    return response($script, 200, ['Content-Type' => 'text/plain']);
});
