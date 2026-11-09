{{ __('Weekly snapshot for :team', ['team' => $team->name]) }}

{{ __(':open leads still open. :won won this week. Outstanding invoices: :outstanding.', [
    'open' => $stats['openLeads'],
    'won' => $stats['wonThisWeek'],
    'outstanding' => $stats['outstanding'],
]) }}

{{ __('Open the dashboard:') }}
{{ $stats['dashboardUrl'] }}

{{ __('View reports:') }}
{{ $stats['reportsUrl'] }}
