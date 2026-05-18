<?php

namespace App\Console\Commands;

use App\Models\CommunityPost;
use App\Support\CountryDisplay;
use Illuminate\Console\Command;

class SyncCommunityContributorCountries extends Command
{
    protected $signature = 'community:sync-contributor-countries';

    protected $description = 'Backfill community_posts.contributor_country from linked contributor users';

    public function handle(): int
    {
        $updated = 0;
        $skipped = 0;

        CommunityPost::query()
            ->whereNotNull('contributor_user_id')
            ->with('contributor:id,country')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use (&$updated, &$skipped): void {
                foreach ($posts as $post) {
                    $country = CountryDisplay::normalizeForStorage($post->contributor?->country);
                    if ($country === null) {
                        $skipped++;

                        continue;
                    }

                    if ($post->contributor_country === $country) {
                        continue;
                    }

                    $post->forceFill(['contributor_country' => $country])->save();
                    $updated++;
                }
            });

        $this->info("Updated {$updated} post(s). Skipped {$skipped} post(s) with no contributor country.");

        return self::SUCCESS;
    }
}
