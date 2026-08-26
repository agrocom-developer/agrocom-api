<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature extienden el TestCase de Laravel (levantan la app);
| los de Unit son PHPUnit puro. Descripciones de tests en español, igual
| que el vocabulario de dominio (CLAUDE.md).
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
