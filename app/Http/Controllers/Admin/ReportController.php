<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use App\Models\ServiceProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $start = now()->subMonths(11)->startOfMonth();

        $monthKeys = $this->monthKeys($start);
        $monthLabels = $this->monthLabels($start);

        $usersByMonth = $this->monthlyCounts(User::class, $start);
        $providersByMonth = $this->monthlyCounts(ServiceProvider::class, $start);
        $leadsByMonth = $this->monthlyCounts(Lead::class, $start);
        $reviewsByMonth = $this->monthlyCounts(Review::class, $start);

        $leadStatusRows = Lead::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $leadStatusLabels = [];
        $leadStatusValues = [];
        foreach (LeadStatus::cases() as $case) {
            $leadStatusLabels[] = $case->label();
            $leadStatusValues[] = (int) ($leadStatusRows[$case->value] ?? 0);
        }

        $reviewRatingRows = Review::query()
            ->selectRaw('rating, COUNT(*) as c')
            ->whereNotNull('rating')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('c', 'rating');

        $ratingLabels = [];
        $ratingValues = [];
        for ($r = 1; $r <= 5; $r++) {
            $ratingLabels[] = $r.' ★';
            $ratingValues[] = (int) ($reviewRatingRows[$r] ?? 0);
        }

        $now = now();
        $usersLast30 = User::query()->where('created_at', '>=', $now->copy()->subDays(30))->count();
        $usersPrev30 = User::query()
            ->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])
            ->count();

        $leadsLast30 = Lead::query()->where('created_at', '>=', $now->copy()->subDays(30))->count();
        $leadsPrev30 = Lead::query()
            ->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])
            ->count();

        return Inertia::render('Admin/Reports/Index', [
            'stats' => [
                'users' => User::count(),
                'providers' => ServiceProvider::count(),
                'providers_verified' => ServiceProvider::query()->where('is_verified', true)->count(),
                'leads' => Lead::count(),
                'reviews' => Review::count(),
                'reviews_approved' => Review::query()->where('is_approved', true)->count(),
                'users_last_30' => $usersLast30,
                'users_prev_30' => $usersPrev30,
                'leads_last_30' => $leadsLast30,
                'leads_prev_30' => $leadsPrev30,
            ],
            'charts' => [
                'months' => [
                    'labels' => $monthLabels,
                    'users' => $this->seriesForKeys($monthKeys, $usersByMonth),
                    'providers' => $this->seriesForKeys($monthKeys, $providersByMonth),
                    'leads' => $this->seriesForKeys($monthKeys, $leadsByMonth),
                    'reviews' => $this->seriesForKeys($monthKeys, $reviewsByMonth),
                ],
                'lead_status' => [
                    'labels' => $leadStatusLabels,
                    'values' => $leadStatusValues,
                ],
                'review_ratings' => [
                    'labels' => $ratingLabels,
                    'values' => $ratingValues,
                ],
            ],
        ]);
    }

    public function users(): Response
    {
        return $this->index();
    }

    public function leads(): Response
    {
        return $this->index();
    }

    public function revenue(): Response
    {
        return $this->index();
    }

    /**
     * @return list<string> Y-m keys
     */
    protected function monthKeys(Carbon $start): array
    {
        $keys = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    /**
     * @return list<string> Short labels e.g. "Apr '25"
     */
    protected function monthLabels(Carbon $start): array
    {
        $labels = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $labels[] = $cursor->format('M \'y');
            $cursor->addMonth();
        }

        return $labels;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array<string, int> keyed by Y-m
     */
    protected function monthlyCounts(string $modelClass, Carbon $start): array
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $driver = DB::getDriverName();

        $expr = match ($driver) {
            'mysql' => "DATE_FORMAT(`{$table}`.`created_at`, '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', \"{$table}\".\"created_at\")",
            'pgsql' => "to_char(\"{$table}\".\"created_at\", 'YYYY-MM')",
            default => "strftime('%Y-%m', \"{$table}\".\"created_at\")",
        };

        return $modelClass::query()
            ->where("{$table}.created_at", '>=', $start)
            ->selectRaw("{$expr} as ym, COUNT(*) as c")
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c', 'ym')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, int>  $counts
     * @return list<int>
     */
    protected function seriesForKeys(array $keys, array $counts): array
    {
        return array_map(fn (string $k) => (int) ($counts[$k] ?? 0), $keys);
    }
}
