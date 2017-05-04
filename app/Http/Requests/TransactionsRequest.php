<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TransactionsRequest extends FormRequest
{
      /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'receiver_abn' => 'required|abn',
            'endpoint' => 'required',
            'document_id' => 'required',

            'document' => 'required_without:template_file',
            'template_file' => 'required_without:document',
        ];

        if ($this->request->get('template_file')) {
            $rules['template_file'] = 'required_without:document|file';
        }

        if ($this->request->get('document')) {
            $rules['document'] = 'required_without:template_file|json';
        }

        return $rules;
    }
}
