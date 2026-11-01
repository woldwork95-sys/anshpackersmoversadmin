<?php

use App\Libraries\AdminAuthenticator;
use App\Libraries\BlockSeo;
use App\Models\AdminModel;
use App\Models\BlockModel;
use App\Models\CategoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\App;
use Config\Services;

/**
 * @internal
 */
final class BlockSeoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;

    private string $csrfHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csrfHash = bin2hex(random_bytes(16));
        $this->adminId  = (int) model(AdminModel::class)->insert([
            'username'      => 'admin',
            'email'         => 'admin@example.com',
            'password_hash' => password_hash('Correct-Horse-9', PASSWORD_DEFAULT),
        ]);
    }

    protected function tearDown(): void
    {
        service('superglobals')->setFilesArray([]);
        parent::tearDown();
    }

    public function testPublishedPageUsesSavedMetadataOnce(): void
    {
        $categoryId = $this->createCategory();
        $breakout   = '"><script>alert(1)</script>';
        $created    = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', '<p>Wrap the utensils first.</p>', 'published', [
            'meta_title'       => $breakout,
            'meta_description' => 'Pack the kitchen before moving day.',
            'og_title'         => 'Kitchen packing',
            'og_description'   => 'How the kitchen is packed.',
            'robots_index'     => 'index',
            'robots_follow'    => 'follow',
            'og_image'         => '../../evil.php',
        ]));
        $created->assertRedirectTo(site_url('admin/blocks'));

        $row = $this->db->table('blocks')->where('slug', 'kitchen-pack')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame($breakout, $row['meta_title']);
        $this->assertSame('Pack the kitchen before moving day.', $row['meta_description']);
        $this->assertNull($row['og_image']);

        service('superglobals')->setServer('HTTP_HOST', 'evil.example');
        service('superglobals')->setServer('SERVER_NAME', 'evil.example');

        $canonical = service('blockSeo')->publicUrl('kitchen-pack');
        $base      = rtrim(config(App::class)->baseURL, '/');
        $this->assertStringStartsWith($base, $canonical);
        $this->assertStringEndsWith('/blog/kitchen-pack', $canonical);
        $this->assertSame(parse_url($base, PHP_URL_HOST), parse_url($canonical, PHP_URL_HOST));
        $this->assertStringNotContainsString('evil.example', $canonical);

        $legacy = $this->get('/blocks/kitchen-pack');
        $legacy->assertStatus(301);
        $legacy->assertHeader('Location', $canonical);

        $page = $this->get('/blog/kitchen-pack');
        $page->assertOK();
        $html = (string) $page->response()->getBody();
        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'name="description"'));
        $this->assertSame(1, substr_count($html, 'name="robots"'));
        $this->assertSame(1, substr_count($html, 'property="og:title"'));
        $this->assertSame(1, substr_count($html, 'property="og:description"'));
        $this->assertSame(1, substr_count($html, 'property="og:url"'));
        $this->assertSame(0, substr_count($html, 'property="og:image"'));
        $this->assertStringContainsString('href="' . $canonical . '"', $html);
        $this->assertStringContainsString('content="' . $canonical . '"', $html);
        $this->assertStringContainsString('content="index, follow"', $html);
        $this->assertStringContainsString('content="Pack the kitchen before moving day."', $html);
        $this->assertStringContainsString('content="Kitchen packing"', $html);
        $this->assertStringContainsString('content="How the kitchen is packed."', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString($breakout, $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('evil.php', $html);

        $preview = $this->asAdmin()->get('/admin/blocks/' . $row['id']);
        $preview->assertOK();
        $admin = (string) $preview->response()->getBody();
        $this->assertSame(0, substr_count($admin, 'rel="canonical"'));
        $this->assertSame(0, substr_count($admin, 'name="description"'));
        $this->assertStringContainsString($canonical, $admin);
    }

    public function testEmptyMetadataUsesTheTitleAndExcerpt(): void
    {
        $words = trim(str_repeat('packed boxes ', 20));
        $seo   = (new BlockSeo())->forBlock([
            'title'   => '  Kitchen   pack ',
            'slug'    => 'kitchen-pack',
            'content' => '<p>' . $words . '</p>',
        ]);

        $this->assertSame('Kitchen pack', $seo['title']);
        $this->assertSame('Kitchen pack', $seo['ogTitle']);
        $this->assertLessThanOrEqual(BlockSeo::EXCERPT_LIMIT, strlen($seo['description']));
        $this->assertStringStartsWith('packed boxes', $seo['description']);
        $this->assertStringNotContainsString('<p>', $seo['description']);
        $this->assertSame($seo['description'], $seo['ogDescription']);
        $this->assertSame('index, follow', $seo['robots']);
        $this->assertSame('', (new BlockSeo())->forBlock(['title' => 'Bare', 'slug' => 'bare', 'content' => ''])['description']);

        $categoryId = $this->createCategory();
        $created    = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Bare page', 'bare-page', '', 'published'));
        $created->assertRedirectTo(site_url('admin/blocks'));
        $page = $this->get('/blog/bare-page');
        $page->assertOK();
        $html = (string) $page->response()->getBody();
        $this->assertSame(0, substr_count($html, 'name="description"'));
        $this->assertSame(0, substr_count($html, 'property="og:description"'));
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertStringContainsString('<title>Bare page | Ansh Packers &amp; Movers</title>', $html);
    }

    public function testValidationRejectsLongMetadataDuplicateSlugsAndBadRobots(): void
    {
        $categoryId = $this->createCategory();
        $long       = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published', [
            'meta_title'       => str_repeat('a', 71),
            'meta_description' => str_repeat('b', BlockSeo::DESCRIPTION_LIMIT + 1),
            'og_title'         => str_repeat('c', 71),
            'og_description'   => str_repeat('d', BlockSeo::DESCRIPTION_LIMIT + 1),
        ]));
        $long->assertRedirectTo(site_url('admin/blocks/create'));
        $long->assertSessionHas('errors');
        $this->assertSame(0, $this->db->table('blocks')->countAllResults());

        $badRobots = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published', [
            'robots_index'  => 'all',
            'robots_follow' => 'nofollow',
        ]));
        $badRobots->assertRedirectTo(site_url('admin/blocks/create'));
        $this->assertSame(0, $this->db->table('blocks')->countAllResults());

        $first = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published', [
            'robots_index' => '',
            'robots_follow' => '',
        ]));
        $first->assertRedirectTo(site_url('admin/blocks'));
        $row = $this->db->table('blocks')->where('slug', 'kitchen-pack')->get()->getRowArray();
        $this->assertSame('index', $row['robots_index']);
        $this->assertSame('follow', $row['robots_follow']);

        $description = trim(str_repeat('Packed carefully. ', 15));
        $this->assertGreaterThan(BlockSeo::EXCERPT_LIMIT, strlen($description));
        $saved = $this->postAdmin('/admin/blocks/' . (int) $row['id'], $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published', [
            'meta_description' => $description,
        ]));
        $saved->assertRedirectTo(site_url('admin/blocks'));
        $again = $this->db->table('blocks')->where('id', (int) $row['id'])->get()->getRowArray();
        $this->assertSame($description, $again['meta_description']);

        $duplicate = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Other pack', 'kitchen-pack', 'Other.', 'draft'));
        $duplicate->assertRedirectTo(site_url('admin/blocks/create'));
        $duplicate->assertSessionHas('errors');
        $this->assertSame(1, $this->db->table('blocks')->countAllResults());
    }

    public function testSlugChangesRedirectOnlyPublishedPages(): void
    {
        $categoryId = $this->createCategory();
        $created    = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published'));
        $created->assertRedirectTo(site_url('admin/blocks'));
        $id = (int) $this->db->table('blocks')->where('slug', 'kitchen-pack')->get()->getRowArray()['id'];

        $renamed = $this->postAdmin('/admin/blocks/' . $id, $this->fields($categoryId, 'Kitchen pack', 'kitchen-boxes', 'Wrap first.', 'published'));
        $renamed->assertRedirectTo(site_url('admin/blocks'));

        $old = $this->get('/blog/kitchen-pack');
        $old->assertStatus(301);
        $old->assertHeader('Location', service('blockSeo')->publicUrl('kitchen-boxes'));

        $current = $this->get('/blog/kitchen-boxes');
        $current->assertOK();
        $this->assertStringContainsString(service('blockSeo')->publicUrl('kitchen-boxes'), (string) $current->response()->getBody());

        $taken = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Second pack', 'kitchen-pack', 'Other.', 'published'));
        $taken->assertRedirectTo(site_url('admin/blocks/create'));
        $taken->assertSessionHas('errors');

        $restored = $this->postAdmin('/admin/blocks/' . $id, $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published'));
        $restored->assertRedirectTo(site_url('admin/blocks'));
        $this->get('/blog/kitchen-pack')->assertOK();
        $moved = $this->get('/blog/kitchen-boxes');
        $moved->assertStatus(301);
        $moved->assertHeader('Location', service('blockSeo')->publicUrl('kitchen-pack'));

        $drafted = $this->postAdmin('/admin/blocks/' . $id, $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'draft'));
        $drafted->assertRedirectTo(site_url('admin/blocks'));
        $this->assertMissing('/blog/kitchen-pack');
        $this->assertMissing('/blog/kitchen-boxes');

        model(BlockModel::class, false)->delete($id);
        $this->assertSame(0, $this->db->table('block_slug_redirects')->countAllResults());
    }

    public function testSitemapListsOnlyIndexablePublicPages(): void
    {
        $categoryId = $this->createCategory();
        $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published'));
        $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Hidden pack', 'hidden-pack', 'Private.', 'published', [
            'robots_index'  => 'noindex',
            'robots_follow' => 'nofollow',
        ]));
        $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Draft pack', 'draft-pack', 'Not ready.', 'draft'));

        $hidden = $this->get('/blog/hidden-pack');
        $hidden->assertOK();
        $this->assertStringContainsString('content="noindex, nofollow"', (string) $hidden->response()->getBody());
        $this->assertMissing('/blog/draft-pack');

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOK();
        $this->assertStringContainsString('application/xml', $sitemap->response()->getHeaderLine('Content-Type'));
        $xml = (string) $sitemap->response()->getBody();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertSame(2, substr_count($xml, '<loc>'));
        $this->assertStringContainsString('<loc>' . site_url('blog', null, config(App::class)) . '</loc>', $xml);
        $this->assertStringContainsString('<loc>' . service('blockSeo')->publicUrl('kitchen-pack') . '</loc>', $xml);
        $this->assertStringContainsString('<lastmod>', $xml);
        $this->assertStringNotContainsString('hidden-pack', $xml);
        $this->assertStringNotContainsString('draft-pack', $xml);
        $this->assertStringNotContainsString(rtrim(config(App::class)->baseURL, '/') . '/</loc>', $xml);
        $this->assertStringNotContainsString('welcome', strtolower($xml));

        $home = $this->get('/');
        $home->assertStatus(301);
        $home->assertHeader('Location', site_url('blog', null, config(App::class)));

        $blog = $this->get('/blog');
        $blog->assertOK();
        $blogHtml = (string) $blog->response()->getBody();
        $this->assertSame(1, substr_count($blogHtml, 'name="description"'));
        $this->assertSame(1, substr_count($blogHtml, 'name="robots"'));
        $this->assertStringContainsString('content="index, follow"', $blogHtml);
        $this->assertSame(1, substr_count($blogHtml, 'rel="canonical"'));
        $this->assertStringContainsString('Kitchen pack', $blogHtml);
        $this->assertStringContainsString('Hidden pack', $blogHtml);
        $this->assertStringContainsString('Admin', $blogHtml);
        $this->assertStringNotContainsString('Draft pack', $blogHtml);

        $login = $this->withSession([])->get('/admin/login');
        $login->assertOK();
        $loginHtml = (string) $login->response()->getBody();
        $this->assertSame(1, substr_count($loginHtml, 'name="robots"'));

        $create = $this->asAdmin()->get('/admin/blocks/create');
        $create->assertOK();
        $form = (string) $create->response()->getBody();
        $this->assertSame(1, substr_count($form, 'name="robots"'));
        $this->assertSame(0, substr_count($form, 'rel="canonical"'));
        $this->assertStringContainsString('name="meta_title"', $form);
        $this->assertStringNotContainsString('name="meta_title" type="text" maxlength="70" disabled', $form);
    }

    public function testOpenGraphImageIsUploadedAndRemoved(): void
    {
        $categoryId = $this->createCategory();
        service('superglobals')->setFilesArray([
            'og_image' => $this->jpegSpec('share.jpg'),
        ]);
        $created = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published'));
        $created->assertRedirectTo(site_url('admin/blocks'));

        $row = $this->db->table('blocks')->where('slug', 'kitchen-pack')->get()->getRowArray();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.jpg$/', (string) $row['og_image']);
        $path = FCPATH . 'uploads/seo/' . $row['og_image'];
        $this->assertFileExists($path);

        $image = rtrim(config(App::class)->baseURL, '/') . '/uploads/seo/' . $row['og_image'];
        $this->assertSame($image, service('blockSeo')->imageUrl((string) $row['og_image']));
        $page = $this->get('/blog/kitchen-pack');
        $html = (string) $page->response()->getBody();
        $this->assertSame(1, substr_count($html, 'property="og:image"'));
        $this->assertStringContainsString('content="' . $image . '"', $html);
        $this->assertStringNotContainsString('share.jpg', $html);

        $id = (int) $row['id'];
        service('superglobals')->setFilesArray([]);
        $removed = $this->postAdmin('/admin/blocks/' . $id, $this->fields($categoryId, 'Kitchen pack', 'kitchen-pack', 'Wrap first.', 'published', [
            'remove_og_image' => '1',
        ]));
        $removed->assertRedirectTo(site_url('admin/blocks'));
        $again = $this->db->table('blocks')->where('id', $id)->get()->getRowArray();
        $this->assertNull($again['og_image']);
        $this->assertFileDoesNotExist($path);
        $cleared = (string) $this->get('/blog/kitchen-pack')->response()->getBody();
        $this->assertSame(0, substr_count($cleared, 'property="og:image"'));
    }

    public function testMissingPagesUseNotFoundStatus(): void
    {
        $this->assertMissing('/blog/not-a-page');
        $this->assertMissing('/blog/NOT_A_PAGE');

        $html = view('errors/html/error_404', ['message' => 'Missing']);
        $this->assertSame(1, substr_count($html, 'name="robots"'));
        $this->assertStringContainsString('noindex, nofollow', $html);
    }

    private function assertMissing(string $path): void
    {
        try {
            $this->get($path);
            $this->fail('Missing page was rendered: ' . $path);
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }
    }

    private function asAdmin(): self
    {
        return $this->withSession([
            AdminAuthenticator::FLAG     => true,
            AdminAuthenticator::ID       => $this->adminId,
            AdminAuthenticator::USERNAME => 'admin',
            'csrf_test_name'             => $this->csrfHash,
        ]);
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function fields(int $categoryId, string $title, string $slug, string $content, string $status, array $extra = []): array
    {
        return array_merge([
            'title'       => $title,
            'slug'        => $slug,
            'content'     => $content,
            'status'      => $status,
            'category_id' => (string) $categoryId,
        ], $extra);
    }

    /**
     * @param array<string, string> $fields
     */
    private function postAdmin(string $path, array $fields): TestResponse
    {
        Services::resetSingle('security');

        return $this->asAdmin()->post($path, array_merge([
            'csrf_test_name' => $this->csrfHash,
        ], $fields));
    }

    private function createCategory(): int
    {
        return (int) model(CategoryModel::class, false)->insert([
            'name'   => 'Local',
            'slug'   => 'local',
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jpegSpec(string $name): array
    {
        $directory = WRITEPATH . 'tests';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path  = $directory . DIRECTORY_SEPARATOR . $name;
        $image = imagecreatetruecolor(16, 16);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return [
            'name'     => $name,
            'type'     => 'image/jpeg',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];
    }
}
