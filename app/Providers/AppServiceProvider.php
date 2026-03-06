<?php

namespace App\Providers;

use Illuminate\Http\Response;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Response::macro('success', function ($data = [], $message = 'Success', int $code = Response::HTTP_OK) {
            return response()->json(array_filter([
                'success' => true,
                'message' => $message,
                'data' => $data
            ], fn($value) => !is_null($value) && !empty($value)), $code);
        });

        Response::macro('error', function ($message = 'Error', int $code = Response::HTTP_BAD_REQUEST, $errors = null) {
            return response()->json(array_filter([
                'success' => false,
                'message' => $message,
                'errors' => $errors
            ], fn($value) => !is_null($value) && !empty($value)), $code);
        });
    }
}
