<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(App\User::class, function (Faker\Generator $faker) {
    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
    ];
});


$factory->define(App\Transaction::class, function (Faker\Generator $faker) {

    return [
        'from_party' => $faker->name,
        'to_party' => $faker->name,
        'user_id' => \App\User::first()->id,
        'message_hash' => str_random(5),
        'encripted_payload' => bcrypt(str_random(10)),
        'decripted_payload' => bcrypt(str_random(10)),
        'notarized_message' => $faker->sentence,
        'message_type' => str_random(10),
        'schema' => str_random(10),
        'validation_status' => $faker->word,
        'validation_message' => $faker->sentence,
    ];
});