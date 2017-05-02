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

            'document' => 'required_without_all:template_id,template_file',
            'template_id' => 'required_without_all:document,template_file',
            'template_file' => 'required_without_all:document,template_id',
        ];

        if ($this->request->get('template_file')) {
            $rules['template_file'] = 'required_without_all:document,template_id|file';
        }

        if ($this->request->get('document')) {
            $rules['document'] = 'required_without_all:template_id,template_file|json';
        }
        if ($this->request->get('template_id')) {
            $rules['template_id'] = 'required_without_all:document,template_file|exists:message_templates,id';
        }

        return $rules;
    }
}
