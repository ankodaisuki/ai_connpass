<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Mail\Mailable;

arch('models extend Eloquent Model')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeIntBackedEnum();

arch('request classes extend FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch('mail classes extend Mailable')
    ->expect('App\Mail')
    ->toExtend(Mailable::class);

arch('controllers have Controller suffix')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('policies have Policy suffix')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('services do not use controllers')
    ->expect('App\Services')
    ->not->toUse('App\Http\Controllers');

arch('no debug functions in application code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ddd', 'ray']);
