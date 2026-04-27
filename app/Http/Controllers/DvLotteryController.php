<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DvLotteryController extends Controller
{
    public function adminIndex(): Response
    {
        return Inertia::render('Admin/DvLottery/Index', [
            'content' => $this->content(),
        ]);
    }

    public function userIndex(): Response
    {
        return Inertia::render('User/DvLottery/Index', [
            'content' => $this->content(),
        ]);
    }

    public function providerIndex(): Response
    {
        return Inertia::render('Provider/DvLottery/Index', [
            'content' => $this->content(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'official_url' => ['required', 'url', 'max:255'],
            'cta_label' => ['required', 'string', 'max:120'],
            'warning_text' => ['required', 'string', 'max:1000'],
        ]);

        $settings = PlatformSetting::current();
        $settings->update([
            'dv_lottery_page_title' => $validated['title'],
            'dv_lottery_page_subtitle' => $validated['subtitle'],
            'dv_lottery_description' => $validated['description'],
            'dv_lottery_official_url' => $validated['official_url'],
            'dv_lottery_cta_label' => $validated['cta_label'],
            'dv_lottery_warning_text' => $validated['warning_text'],
        ]);

        return back()->with('success', 'DV Lottery content updated successfully.');
    }

    private function content(): array
    {
        return PlatformSetting::current()->dvLotteryContent();
    }
}
