<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'confirm_password',
        'new_password',
        'id_capture',
        'selfie_capture',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // A body over PHP's post_max_size arrives empty — explain it instead of
        // showing a bare 413 (the membership form carries two photos).
        $this->renderable(function (PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return null;
            }
            return redirect()->back()->withErrors(['Your files were too large to upload. Please use photos smaller than 5 MB each.']);
        });

        // 419 "Page Expired": the form was opened before the session ended (idle
        // sign-out, logout in another tab, Back button). Send the user to a fresh
        // form with a short note instead of a dead-end error page.
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
