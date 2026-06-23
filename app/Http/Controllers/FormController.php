<?php

namespace App\Http\Controllers;

use App\Forms\FormCatalog;
use App\Models\Lab;

class FormController extends Controller
{
    // Generic dispatcher for catalog-driven form wizards. Invokable controllers under
    // /labs/{lab} MUST take Lab $lab first or Laravel 404s the dispatch.
    public function __invoke(Lab $lab, string $code)
    {
        abort_unless(array_key_exists($code, FormCatalog::FORMS), 404);
        $component = FormCatalog::FORMS[$code]['component'] ?? null;
        abort_unless(in_array($component, ['form-wizard', 'log-form'], true), 404);

        return view('forms.generic', ['lab' => $lab, 'code' => $code, 'component' => $component]);
    }
}
