<?php
declare(strict_types=1);

if (getenv('FANDOOGH_TEST_DATABASE') !== 'fandoogh_web_acceptance_test') {
    throw new LogicException('Only the separate web acceptance database is allowed.');
}
require '/var/www/html/tests/bootstrap.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\SafeTestEnvironment::assert($app, 'fandoogh_web_acceptance_test');
$mode = $argv[1] ?? 'fixture';
if ($mode === 'inspect') {
    echo json_encode(['database' => Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name,
        'public_only' => App\Models\PropertyPublication::query()->where('public_title', 'عنوان مستقل آزمایشی')->first(['share_with_agencies', 'publish_public', 'public_title'])->toArray(),
        'browser_text_messages' => App\Models\MarketplaceMessage::query()->where('body', 'پیام ارسالی واقعی از مرورگر آزمون')->count(),
        'image_messages' => App\Models\MarketplaceMessage::query()->whereNotNull('image_path')->count()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    exit;
}
if ($mode === 'close') {
    App\Models\PropertyPublication::query()->where('public_title', 'آگهی آزمون مستقل وب')->update(['share_with_agencies' => false, 'publish_public' => false]);
    echo "QA publication closed\n";
    exit;
}
if ($mode !== 'fixture' || Illuminate\Support\Facades\Schema::hasTable('users')) {
    throw new LogicException('Fixture requires a new empty QA schema.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => Database\Seeders\RolesAndPermissionsSeeder::class, '--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => Database\Seeders\IranLocationsSeeder::class, '--force' => true]);
$city = Illuminate\Support\Facades\DB::table('location_cities')->first();
$county = Illuminate\Support\Facades\DB::table('location_counties')->where('id', $city->county_id)->first();
$geo = ['province_id' => $county->province_id, 'county_id' => $county->id, 'city_id' => $city->id];
$viewer = App\Models\Agency::factory()->active()->create(['name' => 'آژانس مشاهده آزمون', 'is_verified' => true, ...$geo]);
$publisher = App\Models\Agency::factory()->active()->create(['name' => 'آژانس انتشار آزمون', 'phone' => '02112340000', 'is_verified' => true, ...$geo]);
$viewerManager = App\Models\User::factory()->agencyManager($viewer)->create(['email' => 'viewer@webqa.local', 'name' => 'مدیر مشاهده آزمون']);
$publisherManager = App\Models\User::factory()->agencyManager($publisher)->create(['email' => 'publisher@webqa.local', 'name' => 'مدیر انتشار آزمون']);
$advisor = App\Models\User::factory()->agent($publisher)->create(['email' => 'advisor@webqa.local', 'name' => 'مشاور پاسخگو آزمون']);
app(App\Domain\Tenancy\TenantContext::class)->establish($viewerManager);
$own = App\Models\Property::factory()->forAgency($viewer, $viewerManager)->create([...$geo, 'title' => 'PRIVATE INTERNAL TITLE', 'district' => 'محله ملک داخلی آزمون']);
app(App\Domain\Tenancy\TenantContext::class)->establish($publisherManager);
$property = App\Models\Property::factory()->forAgency($publisher, $publisherManager)->create([...$geo, 'title' => 'SECRET OWNER TITLE', 'street_address' => 'SECRET PRIVATE ADDRESS', 'postal_code' => '1111122222', 'district' => 'محله قابل نمایش آزمون', 'currency_unit' => 'toman', 'sale_price' => '7500000000.00', 'area_sqm' => '95.50']);
$pixels = imagecreatetruecolor(80, 50);
imagefill($pixels, 0, 0, imagecolorallocate($pixels, 25, 100, 150));
ob_start(); imagejpeg($pixels); $bytes = ob_get_clean(); imagedestroy($pixels);
Illuminate\Support\Facades\Storage::disk('local')->put('qa/listing.jpg', $bytes);
$image = App\Models\PropertyImage::query()->create(['property_id' => $property->id, 'uploaded_by_user_id' => $publisherManager->id, 'storage_path' => 'qa/listing.jpg', 'original_name' => 'qa-private.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => strlen($bytes), 'width' => 80, 'height' => 50, 'is_cover' => true]);
$listing = app(App\Application\Marketplace\Services\PublicationService::class)->save($publisherManager, $property, ['share_with_agencies' => true, 'publish_public' => true, 'public_title' => 'آگهی آزمون مستقل وب', 'public_description' => 'توضیح عمومی بازبینی شده برای آزمون', 'image_ids' => [$image->id], 'responding_user_id' => $advisor->id]);
app(App\Domain\Tenancy\TenantContext::class)->establish($viewerManager);
$chat = app(App\Application\Marketplace\Services\ConversationService::class)->start($viewerManager, $listing->id, 'agency');
app(App\Application\Marketplace\Services\ConversationService::class)->send($chat, $viewerManager, 'پیام اولیه آزمون وب', null, 'webqa-seed-text');
$upload = Illuminate\Http\UploadedFile::fake()->image('qa-chat.jpg', 40, 30);
app(App\Application\Marketplace\Services\ConversationService::class)->send($chat, $viewerManager, 'پیام تصویری آزمون', $upload, 'webqa-seed-photo');
echo json_encode(['own_property' => $own->id, 'listing' => $listing->id, 'conversation' => $chat->id, 'geo' => $geo], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
