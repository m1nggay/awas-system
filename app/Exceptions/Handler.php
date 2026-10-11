<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{

    protected $levels = [

    ];

    protected $dontReport = [

    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'confirm_password',
        'new_password',
        'id_capture',
        'selfie_capture',
    ];


    public function register(): void
    {
        $this->reportable(function (Throwable $e) {

        });

        $this->renderable(function (PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return null;
            }
            return redirect()->back()->withErrors(['Your files were too large to upload. Please use photos smaller than 5 MB each.']);
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }
            $message = 'Your page expired. Please try again.';
            if (!$request->user()) {
                return redirect()->route('login')
                    ->withInput($request->only('username'))
                    ->with('error', 'Your session expired. Please sign in again.');
            }
            flash('warning', $message);
            return redirect()->back();
        });
    }
}
