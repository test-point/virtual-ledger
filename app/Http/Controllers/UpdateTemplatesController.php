<?php

namespace App\Http\Controllers;

use App\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateTemplatesController extends Controller
{
    public function updateInvoiceSamples()
    {
         $templatesPath = resource_path('data/templates');
        //git remote add -f origin https://github.com/ausdigital/ausdigital-bill
        //git config core.sparseCheckout true
        //echo "resources/ausdigital-syn/2.0/samples/Invoice/" >> .git/info/sparse-checkout
        //git pull origin master
//        dump("cd $templatesPath && git pull origin master");
//        runConsoleCommand("cd $templatesPath && git pull origin master");

        dump($templatesPath . '/resources/ausdigital-syn/2.0/samples/Invoice');

        $storage = Storage::disk('templates');
        foreach($storage->files() as $file){
            MessageTemplate::updateOrCreate(['name'=> $file],['content'=> $storage->get($file)]);
        }
    }
}
