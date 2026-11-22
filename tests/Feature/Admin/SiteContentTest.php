<?php

use App\Filament\Pages\ManageSiteContent;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Support\SiteContent;
use Database\Seeders\PageSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed([SettingSeeder::class, PageSeeder::class]);
});

function putTexts(array $texts): void
{
    Setting::put(SiteContent::TEXTS_SETTING, json_encode($texts, JSON_UNESCAPED_UNICODE));
}

describe('storefront texts', function () {
    it('shows the owner\'s wording in place of the built-in line', function () {
        $builtIn = __('shop.hero.tagline_1', [], 'ar');
        putTexts(['ar' => ['hero' => ['tagline_1' => 'أناقة تدوم']], 'en' => ['hero' => ['tagline_1' => 'Lasting elegance']]]);

        $this->get('/')->assertOk()->assertSee('أناقة تدوم')->assertDontSee($builtIn);
        $this->get('/en')->assertOk()->assertSee('Lasting elegance');
    });

    it('keeps every line the owner did not change', function () {
        putTexts(['ar' => ['hero' => ['tagline_1' => 'أناقة تدوم']]]);

        $this->get('/')->assertSee(__('shop.hero.tagline_2', [], 'ar'))->assertSee(__('shop.welcome.enter', [], 'ar'));
        $this->get('/shop')->assertSee(__('shop.nav.offers', [], 'ar'));
    });

    it('uses the built-in line when a field is left blank', function () {
        putTexts(['ar' => ['hero' => ['tagline_1' => '   ']]]);

        $this->get('/')->assertSee(__('shop.hero.tagline_1', [], 'ar'));
    });

    it('fills placeholders in the owner\'s wording', function () {
        putTexts(['ar' => ['announce' => ['whatsapp' => 'راسلنا على :phone']]]);

        $this->get('/shop')->assertSee('راسلنا على 0774 024 3538');
    });

    it('escapes markup typed into a text', function () {
        putTexts(['ar' => ['hero' => ['tagline_1' => '<script>alert(1)</script>']]]);

        $this->get('/')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    });

    it('ignores lines that are not on the editable list', function () {
        putTexts(['ar' => ['checkout' => ['title' => 'مخترق']]]);

        expect(SiteContent::overrides('ar'))->toBe([]);
        $this->get('/checkout')->assertDontSee('مخترق');
    });

    it('survives a broken stored value', function () {
        Setting::put(SiteContent::TEXTS_SETTING, '{not json');

        $this->get('/')->assertOk()->assertSee(__('shop.hero.tagline_1', [], 'ar'));
    });
});

describe('storefront media', function () {
    it('falls back to the theme\'s own files', function () {
        expect(SiteContent::mediaUrl('landing_video_wide'))->toBe(asset('assets/video/landing-wide.mp4'))
            ->and(SiteContent::mediaUrl('home_story'))->toBe(asset('assets/img/demo/story.webp'));
    });

    it('serves the owner\'s upload on the landing page and the home page', function () {
        Storage::disk('public')->put('site/wide.mp4', 'video');
        Storage::disk('public')->put('site/story.jpg', 'image');
        Setting::put(SiteContent::MEDIA_SETTING, json_encode(['landing_video_wide' => 'site/wide.mp4', 'home_story' => 'site/story.jpg']));

        $this->get('/')->assertSee(Storage::disk('public')->url('site/wide.mp4'), false)
            ->assertSee(asset('assets/video/landing-portrait.mp4'), false);
        $this->get('/shop')->assertSee(Storage::disk('public')->url('site/story.jpg'), false);
    });

    it('falls back when the uploaded file has gone', function () {
        Setting::put(SiteContent::MEDIA_SETTING, json_encode(['home_story' => 'site/missing.jpg']));

        expect(SiteContent::mediaUrl('home_story'))->toBe(asset('assets/img/demo/story.webp'));
    });
});

describe('the site content page', function () {
    it('is for the owner only', function () {
        $this->actingAs(staff());

        $this->get(ManageSiteContent::getUrl())->assertForbidden();
        Livewire::test(ManageSiteContent::class)->assertForbidden();
    });

    it('opens for the owner with the built-in wording as placeholders', function () {
        $this->actingAs(owner());

        $this->get(ManageSiteContent::getUrl())->assertOk()
            ->assertSee(__('admin.site_content.title'))
            ->assertSee(__('shop.hero.tagline_1', [], 'ar'));
    });

    it('saves texts, dropping blanks, and the storefront uses them', function () {
        $this->actingAs(owner());

        Livewire::test(ManageSiteContent::class)
            ->fillForm([
                'texts.ar.hero.tagline_1' => 'أناقة تدوم',
                'texts.en.hero.tagline_1' => 'Lasting elegance',
                'texts.ar.footer.about' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(SiteContent::decode(Setting::get(SiteContent::TEXTS_SETTING)))
            ->toBe(['ar' => ['hero' => ['tagline_1' => 'أناقة تدوم']], 'en' => ['hero' => ['tagline_1' => 'Lasting elegance']]]);

        $this->get('/')->assertSee('أناقة تدوم');
    });

    it('shows what was saved when opened again', function () {
        $this->actingAs(owner());
        putTexts(['ar' => ['home' => ['story_cta' => 'تعرّف علينا']]]);

        Livewire::test(ManageSiteContent::class)->assertSchemaStateSet(['texts.ar.home.story_cta' => 'تعرّف علينا']);
    });

    it('uploads a landing video and an image', function () {
        $this->actingAs(owner());

        Livewire::test(ManageSiteContent::class)
            ->fillForm([
                'media.landing_video_wide' => [UploadedFile::fake()->create('wide.mp4', 2048, 'video/mp4')],
                'media.home_story' => [UploadedFile::fake()->image('story.jpg', 1200, 900)],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $media = SiteContent::decode(Setting::get(SiteContent::MEDIA_SETTING));

        expect($media)->toHaveKeys(['landing_video_wide', 'home_story'])
            ->and($media['landing_video_wide'])->toStartWith('site/');
        Storage::disk('public')->assertExists($media['landing_video_wide']);
        $this->get('/')->assertSee(Storage::disk('public')->url($media['landing_video_wide']), false);
    });

    it('refuses a file that is not a web video', function () {
        $this->actingAs(owner());
        // Livewire's own upload rules already refuse it (see HardeningTest); this checks the form's rule behind them.
        config(['livewire.temporary_file_upload.rules' => null]);

        Livewire::test(ManageSiteContent::class)
            ->fillForm(['media.landing_video_wide' => [UploadedFile::fake()->create('clip.mov', 100, 'video/quicktime')]])
            ->call('save')
            ->assertHasFormErrors(['media.landing_video_wide']);
    });

    it('deletes a replaced upload', function () {
        $this->actingAs(owner());
        Storage::disk('public')->put('site/old.jpg', 'old');
        Setting::put(SiteContent::MEDIA_SETTING, json_encode(['home_story' => 'site/old.jpg']));

        Livewire::test(ManageSiteContent::class)
            ->fillForm(['media.home_story' => [UploadedFile::fake()->image('new.jpg', 1200, 900)]])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertMissing('site/old.jpg');
    });
});

describe('page video', function () {
    it('shows no player on a page without a video', function () {
        $this->get('/page/about')->assertOk()->assertDontSee('<video', false);
    });

    it('plays the uploaded video above the text by default', function () {
        Storage::disk('public')->put('pages/about.mp4', 'video');
        Storage::disk('public')->put('pages/about.jpg', 'image');
        Page::query()->where('slug', 'about')->update(['video_path' => 'pages/about.mp4', 'video_poster_path' => 'pages/about.jpg']);

        $html = $this->get('/page/about')->assertOk()->getContent();

        expect($html)->toContain('poster="'.Storage::disk('public')->url('pages/about.jpg').'"')
            ->toContain('<source src="'.Storage::disk('public')->url('pages/about.mp4').'" type="video/mp4">');
        expect(strpos($html, '<video'))->toBeGreaterThan(strpos($html, 'class="listing__title"'))
            ->toBeLessThan(strpos($html, 'class="listing__title"') + 600);
    });

    it('can sit under the text', function () {
        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $page->update(['video_path' => 'pages/about.webm', 'video_position' => Page::VIDEO_BOTTOM, 'body_ar' => '<p>نص الصفحة</p>']);

        $html = $this->get('/page/about')->getContent();

        expect(strpos($html, 'نص الصفحة'))->toBeLessThan(strpos($html, '<video'))
            ->and($html)->toContain('type="video/webm"');
    });

    it('is uploaded from the page form', function () {
        $this->actingAs(owner());
        $page = Page::query()->where('slug', 'about')->firstOrFail();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'video_path' => [UploadedFile::fake()->create('about.mp4', 4096, 'video/mp4')],
                'video_position' => Page::VIDEO_TOP,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();

        expect($page->video_path)->toStartWith('pages/');
        Storage::disk('public')->assertExists($page->video_path);
    });

    it('deletes the old file when the video is replaced or removed', function () {
        Storage::disk('public')->put('pages/old.mp4', 'video');
        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $page->update(['video_path' => 'pages/old.mp4']);

        $page->update(['video_path' => null]);

        Storage::disk('public')->assertMissing('pages/old.mp4');
    });
});

describe('product order', function () {
    beforeEach(function () {
        $this->category = Category::factory()->create(['slug' => 'sofas']);
    });

    function listedSlugs(string $url): array
    {
        /** @var LengthAwarePaginator $products */
        $products = test()->get($url)->assertOk()->viewData('products');

        return $products->getCollection()->pluck('slug')->all();
    }

    it('lists a section in the owner\'s order', function () {
        Product::factory()->for($this->category)->create(['slug' => 'b', 'sort_order' => 2, 'created_at' => now()->subDay()]);
        Product::factory()->for($this->category)->create(['slug' => 'a', 'sort_order' => 1, 'created_at' => now()->subDays(2)]);
        Product::factory()->for($this->category)->create(['slug' => 'c', 'sort_order' => 3, 'created_at' => now()]);

        expect(listedSlugs('/category/sofas'))->toBe(['a', 'b', 'c'])
            ->and(listedSlugs('/category/sofas?sort=newest'))->toBe(['c', 'b', 'a']);
    });

    it('puts a product never placed at the top', function () {
        Product::factory()->for($this->category)->create(['slug' => 'placed', 'sort_order' => 1]);
        Product::factory()->for($this->category)->create(['slug' => 'brand-new', 'sort_order' => 0]);

        expect(listedSlugs('/category/sofas'))->toBe(['brand-new', 'placed']);
    });

    it('lets the owner drag products into order', function () {
        $this->actingAs(owner());
        $first = Product::factory()->for($this->category)->create();
        $second = Product::factory()->for($this->category)->create();

        Livewire::test(ListProducts::class)
            ->call('reorderTable', [(string) $second->getKey(), (string) $first->getKey()]);

        expect($second->refresh()->sort_order)->toBeLessThan($first->refresh()->sort_order);
    });
});
