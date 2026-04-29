<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'short_description' => ['required', 'string', 'max:255'],
            'official_url' => ['required', 'url', 'max:255'],
            'open_from' => ['required', 'date'],
            'open_to' => ['required', 'date', 'after_or_equal:open_from'],
            'show_in_menu_after_close' => ['nullable', 'boolean'],
        ]);

        $settings = PlatformSetting::current();
        $settings->update([
            'dv_lottery_page_title' => $validated['title'],
            'dv_lottery_page_subtitle' => $validated['short_description'],
            'dv_lottery_official_url' => $validated['official_url'],
            'dv_lottery_open_from' => $validated['open_from'],
            'dv_lottery_open_to' => $validated['open_to'],
            'dv_lottery_show_in_menu_after_close' => (bool) ($validated['show_in_menu_after_close'] ?? false),
        ]);

        return back()->with('success', 'DV Lottery content updated successfully.');
    }

    public function closeNow(): RedirectResponse
    {
        PlatformSetting::current()->update([
            'dv_lottery_open_to' => Carbon::today()->subDay()->toDateString(),
        ]);

        return back()->with('success', 'DV Lottery has been marked as closed.');
    }

    public function destroy(): RedirectResponse
    {
        PlatformSetting::current()->update([
            'dv_lottery_page_title' => null,
            'dv_lottery_page_subtitle' => null,
            'dv_lottery_official_url' => null,
            'dv_lottery_open_from' => null,
            'dv_lottery_open_to' => null,
            'dv_lottery_show_in_menu_after_close' => false,
        ]);

        return back()->with('success', 'DV Lottery settings were cleared.');
    }

    private function content(): array
    {
        return PlatformSetting::current()->dvLotteryContent();
    }
}
