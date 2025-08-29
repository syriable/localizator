<?php

declare(strict_types=1);

class SampleController
{
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('dashboard', [
            'title' => __('dashboard.widgets.title'),
            'welcome' => trans('welcome.messages.greeting'),
            'count' => trans_choice('items.lists.count', 5),
        ]);
    }

    public function show(): void
    {
        echo (string) __('auth.login.failed');
        echo (string) trans('validation.custom.email.required');
    }
}
