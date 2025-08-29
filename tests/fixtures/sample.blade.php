<!DOCTYPE html>
<html>
<head>
    <title>@lang('app.meta.title')</title>
</head>
<body>
    <h1>@lang('dashboard.header.welcome')</h1>
    <p>@choice('messages.notifications.items', $itemCount)</p>
    
    <div>{{ __('buttons.forms.submit') }}</div>
    <span>{{ trans('labels.user.name') }}</span>
</body>
</html>