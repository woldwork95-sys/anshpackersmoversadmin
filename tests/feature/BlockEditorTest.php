<?php

use App\Libraries\AdminAuthenticator;
use App\Libraries\BlockImageStore;
use App\Models\AdminModel;
use App\Models\BlockModel;
use App\Models\CategoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * @internal
 */
final class BlockEditorTest extends CIUnitTestCase
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
        $this->removeUploads();
        parent::tearDown();
    }

    public function testEditorFormExposesFormattingAndCollapsedSeo(): void
    {
        $categoryId = $this->createCategory();
        $create     = $this->asAdmin()->get('/admin/blocks/create');
        $create->assertOK();
        $create->assertSee('ckeditor5.js');
        $create->assertSee('block-editor.js');
        $create->assertSee('Save draft');
        $create->assertSee('Publish');
        $create->assertSee('Search appearance');
        $create->assertSee('Empty fields use the block title');
        $create->assertDontSee('>Preview<');

        $html = (string) $create->response()->getBody();
        $this->assertSame(1, preg_match('/<form id="block-form"[\s\S]*?<button\b([^>]*)>/', $html, $match));
        $this->assertStringContainsString('visually-hidden', $match[1]);
        $this->assertStringNotContainsString('name=', $match[1]);

        $script = file_get_contents(FCPATH . 'assets/admin/block-editor.js');
        $this->assertIsString($script);
        $this->assertStringContainsString("'undo'", $script);
        $this->assertStringContainsString("'redo'", $script);
        $this->assertStringContainsString("'sourceEditing'", $script);
        $this->assertStringContainsString("'insertTable'", $script);
        $this->assertStringContainsString("licenseKey: 'GPL'", $script);
        $this->assertStringContainsString('shouldNotGroupWhenFull: true', $script);

        $blockId = $this->createBlock($categoryId, 'draft');
        $edit    = $this->asAdmin()->get('/admin/blocks/' . $blockId . '/edit');
        $edit->assertOK();
        $edit->assertSee('Preview');
        $edit->assertSee('Publish');
    }

    public function testFormattingPersistsAndDraftsStayPrivate(): void
    {
        $categoryId = $this->createCategory();
        $content    = '<h1>Secret heading</h1><h2>Head</h2><p><strong>Bold</strong> and <em>italic</em></p>'
            . '<ul><li>One</li></ul><ol><li>Two</li></ol>'
            . '<p style="text-align: center; background: url(javascript:alert(1))">Mid</p>'
            . '<table><tbody><tr><td colspan="2">Cell</td></tr></tbody></table>'
            . '<a href="https://example.com/move">Site</a>'
            . '<a href="javascript:alert(1)">Bad</a>'
            . '<figure class="image"><img src="/uploads/blocks/' . str_repeat('ab', 16) . '.jpg" alt="Box"><figcaption>Packed box</figcaption></figure>'
            . '<img src="https://evil.example/a.jpg" onerror="alert(1)">'
            . '<iframe src="https://evil.example/embed"></iframe>'
            . '<script>alert(1)</script>';

        $created = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Packing notes', 'packing-notes', $content, 'draft', [
            'seo_title'       => 'SEO-SECRET-NOT-SAVED',
            'seo_description' => 'SEO-SECRET-NOT-SAVED',
        ]));
        $created->assertRedirectTo(site_url('admin/blocks'));

        $row = $this->db->table('blocks')->where('slug', 'packing-notes')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('seo_title', $row);
        $stored = (string) $row['content'];
        $this->assertStringContainsString('<h2>Head</h2>', $stored);
        $this->assertStringContainsString('<strong>Bold</strong>', $stored);
        $this->assertStringContainsString('<em>italic</em>', $stored);
        $this->assertStringContainsString('<ul><li>One</li></ul>', $stored);
        $this->assertStringContainsString('<ol><li>Two</li></ol>', $stored);
        $this->assertStringContainsString('text-align: center', $stored);
        $this->assertStringContainsString('colspan="2"', $stored);
        $this->assertStringContainsString('https://example.com/move', $stored);
        $this->assertStringContainsString('noopener noreferrer', $stored);
        $this->assertStringContainsString('<figure>', $stored);
        $this->assertStringContainsString('<figcaption>Packed box</figcaption>', $stored);
        $this->assertStringContainsString(str_repeat('ab', 16) . '.jpg', $stored);
        $this->assertStringNotContainsString('<h1', $stored);
        $this->assertStringNotContainsString('<script', strtolower($stored));
        $this->assertStringNotContainsString('javascript:', strtolower($stored));
        $this->assertStringNotContainsString('onerror', strtolower($stored));
        $this->assertStringNotContainsString('<iframe', strtolower($stored));
        $this->assertStringNotContainsString('evil.example', $stored);
        $this->assertStringNotContainsString('SEO-SECRET-NOT-SAVED', $stored);
        $this->assertStringNotContainsString('background', $stored);

        $id   = (int) $row['id'];
        $edit = $this->asAdmin()->get('/admin/blocks/' . $id . '/edit');
        $edit->assertOK();
        $edit->assertSee('&lt;strong&gt;Bold&lt;/strong&gt;');
        $edit->assertSee('&lt;h2&gt;Head&lt;/h2&gt;');

        $preview = $this->asAdmin()->get('/admin/blocks/' . $id);
        $preview->assertOK();
        $this->assertStringContainsString('<strong>Bold</strong>', $preview->getBody());
        $preview->assertSee('Mid');
        $preview->assertDontSee('<script>alert(1)</script>');
        $preview->assertDontSee('onerror');
        $preview->assertDontSee('<iframe');

        $this->assertPageMissing(fn () => $this->get('/blog/packing-notes'));

        $published = $this->postAdmin('/admin/blocks/' . $id, $this->fields($categoryId, 'Packing notes', 'packing-notes', $content, 'published'));
        $published->assertRedirectTo(site_url('admin/blocks'));

        $public = $this->get('/blog/packing-notes');
        $public->assertOK();
        $public->assertSee('Packing notes');
        $body = $public->getBody();
        $this->assertStringContainsString('<strong>Bold</strong>', $body);
        $this->assertStringContainsString('<h2>Head</h2>', $body);
        $this->assertStringContainsString('text-align: center', $body);
        $this->assertStringContainsString('Packed box', $body);
        $this->assertStringContainsString('>Local<', $body);
        $public->assertDontSee('<script>alert(1)</script>');
        $public->assertDontSee('javascript:');
        $public->assertDontSee('onerror');
        $public->assertDontSee('<iframe');
        $public->assertDontSee('SEO-SECRET-NOT-SAVED');

        $updated = $this->asAdmin()->get('/admin/blocks/' . $id . '/edit');
        $updated->assertOK();
        $updated->assertSee('Update');

        $drafted = $this->postAdmin('/admin/blocks/' . $id, $this->fields($categoryId, 'Packing notes', 'packing-notes', $stored, 'draft'));
        $drafted->assertRedirectTo(site_url('admin/blocks'));
        $this->assertPageMissing(fn () => $this->get('/blog/packing-notes'));
    }

    public function testUnsafeStoredHtmlIsRemovedWhenRendered(): void
    {
        $categoryId = $this->createCategory();
        $blockId    = $this->createBlock($categoryId, 'published', '<script>alert(1)</script><p onclick="alert(1)">Visible <a href="javascript:alert(1)">link</a></p>');

        $preview = $this->asAdmin()->get('/admin/blocks/' . $blockId);
        $preview->assertOK();
        $preview->assertSee('Visible');
        $preview->assertDontSee('<script>alert(1)</script>');
        $preview->assertDontSee('onclick');
        $preview->assertDontSee('javascript:');

        $public = $this->get('/blog/kept-block');
        $public->assertOK();
        $public->assertSee('A &amp; B &lt;pack&gt;');
        $public->assertSee('Visible');
        $public->assertDontSee('<script>alert(1)</script>');
        $public->assertDontSee('onclick');
        $public->assertDontSee('javascript:');
    }

    public function testEmptyEditorContentIsStoredAsNull(): void
    {
        $categoryId = $this->createCategory();
        $created    = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Empty notes', 'empty-notes', '<p><br></p>', 'draft'));
        $created->assertRedirectTo(site_url('admin/blocks'));

        $row = $this->db->table('blocks')->where('slug', 'empty-notes')->get()->getRowArray();
        $this->assertNull($row['content']);
    }

    public function testImageUploadValidatesAndReencodes(): void
    {
        $guest = $this->post('/admin/blocks/images', []);
        $guest->assertRedirect();
        $this->assertSame([], $this->uploadNames());

        $missing = $this->postImage([]);
        $missing->assertStatus(422);
        $this->assertFalse($this->json($missing)['success']);

        $php = $this->postImage($this->fileSpec(WRITEPATH . 'tests/shell.php', 'shell.php', '<?php echo 1;'));
        $php->assertStatus(422);
        $this->assertStringContainsString('not allowed', implode(' ', $this->json($php)['data']['messages']));
        $this->assertSame([], $this->uploadNames());

        $double = $this->postImage($this->jpegSpec('shell.php.jpg'));
        $double->assertStatus(422);
        $this->assertSame([], $this->uploadNames());

        $text = $this->postImage($this->fileSpec(WRITEPATH . 'tests/note.jpg', 'note.jpg', 'not an image'));
        $text->assertStatus(422);
        $this->assertSame([], $this->uploadNames());

        $svg = $this->postImage($this->fileSpec(WRITEPATH . 'tests/pic.svg', 'pic.svg', '<svg onload="alert(1)"></svg>'));
        $svg->assertStatus(422);

        $huge = $this->jpegSpec('huge.jpg');
        $huge['error'] = UPLOAD_ERR_INI_SIZE;
        $huge['size']  = 99999999;
        $limited = $this->postImage($huge);
        $limited->assertStatus(422);
        $this->assertStringContainsString('too large', implode(' ', $this->json($limited)['data']['messages']));

        $small = new UploadedFile($this->jpegPath('small.jpg'), 'small.jpg', 'image/jpeg', 20, UPLOAD_ERR_OK);
        $rejected = (new BlockImageStore(32))->store($small);
        $this->assertFalse($rejected['ok']);

        $polyglot = $this->postImage($this->jpegSpec('photo.jpg', '<?php echo 1;'));
        $polyglot->assertOK();
        $payload = $this->json($polyglot);
        $this->assertTrue($payload['success']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.jpg$/', $payload['data']['files'][0]);
        $this->assertNotEmpty($payload['csrf']);

        $stored = FCPATH . 'uploads/blocks/' . $payload['data']['files'][0];
        $this->assertFileExists($stored);
        $bytes = file_get_contents($stored);
        $this->assertIsString($bytes);
        $this->assertStringNotContainsString('<?php', $bytes);
        $this->assertNotFalse(getimagesize($stored));

        $src  = $payload['data']['baseurl'] . $payload['data']['files'][0];
        $html = service('blockHtml')->normalize('<img src="' . $src . '" alt="Packed box"><img src="https://evil.example/a.jpg">');
        $this->assertIsString($html);
        $this->assertStringContainsString($payload['data']['files'][0], $html);
        $this->assertStringNotContainsString('evil.example', $html);

        $categoryId = $this->createCategory();
        $saved      = $this->postAdmin('/admin/blocks', $this->fields($categoryId, 'Photo notes', 'photo-notes', (string) $html, 'published'));
        $saved->assertRedirectTo(site_url('admin/blocks'));
        $public = $this->get('/blog/photo-notes');
        $public->assertOK();
        $this->assertStringContainsString($payload['data']['files'][0], $public->getBody());
        $public->assertDontSee('evil.example');

        $withoutToken = $this->withSession([
            AdminAuthenticator::FLAG     => true,
            AdminAuthenticator::ID       => $this->adminId,
            AdminAuthenticator::USERNAME => 'admin',
            'csrf_test_name'             => $this->csrfHash,
        ])->post('/admin/blocks/images', []);
        $withoutToken->assertSessionHas('error', 'The action you requested is not allowed.');
    }

    public function testDirectSanitizerRejectsMaliciousUrls(): void
    {
        $html = service('blockHtml')->render(
            '<a href="data:text/html,hello">Data</a>'
            . '<a href="vbscript:msgbox(1)">Vb</a>'
            . '<a href="../secret">Up</a>'
            . '<img src="data:image/svg+xml;base64,PHN2Zy8+">'
            . '<img src="/uploads/blocks/../../index.php">'
            . '<img src="http://example.com/uploads/blocks/' . str_repeat('a', 32) . '.jpg?x=1">'
            . '<p style="width:expression(alert(1))">Safe text</p>',
        );

        $this->assertStringContainsString('Safe text', $html);
        $this->assertStringNotContainsString('data:', $html);
        $this->assertStringNotContainsString('vbscript:', $html);
        $this->assertStringNotContainsString('../', $html);
        $this->assertStringNotContainsString('expression', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    private function assertPageMissing(callable $request): void
    {
        try {
            $request();
            $this->fail('A draft or missing block was rendered.');
        } catch (PageNotFoundException) {
            $this->assertTrue(true);
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

    /**
     * @param array<string, mixed> $file
     */
    private function postImage(array $file): TestResponse
    {
        Services::resetSingle('security');
        if ($file !== []) {
            service('superglobals')->setFilesArray(['image' => $file]);
        }

        return $this->asAdmin()->post('/admin/blocks/images', [
            'csrf_test_name' => $this->csrfHash,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(TestResponse $response): array
    {
        $decoded = json_decode((string) $response->getJSON(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function createCategory(): int
    {
        return (int) model(CategoryModel::class, false)->insert([
            'name'   => 'Local',
            'slug'   => 'local',
            'status' => 'active',
        ]);
    }

    private function createBlock(int $categoryId, string $status, string $content = 'Visible text'): int
    {
        return (int) model(BlockModel::class, false)->insert([
            'category_id' => $categoryId,
            'title'       => 'A & B <pack>',
            'slug'        => 'kept-block',
            'content'     => $content,
            'status'      => $status,
        ]);
    }

    private function jpegPath(string $name, string $append = ''): string
    {
        $directory = WRITEPATH . 'tests';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path  = $directory . DIRECTORY_SEPARATOR . $name;
        $image = imagecreatetruecolor(16, 16);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
        if ($append !== '') {
            file_put_contents($path, $append, FILE_APPEND);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function jpegSpec(string $name, string $append = ''): array
    {
        $path = $this->jpegPath($name, $append);

        return [
            'name'     => $name,
            'type'     => 'image/jpeg',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileSpec(string $path, string $name, string $contents): array
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $contents);

        return [
            'name'     => $name,
            'type'     => 'application/octet-stream',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path),
        ];
    }

    /**
     * @return list<string>
     */
    private function uploadNames(): array
    {
        $names = [];
        foreach (glob(FCPATH . 'uploads/blocks/*') ?: [] as $path) {
            if (is_file($path) && preg_match('/^[a-f0-9]{32}\./', basename($path)) === 1) {
                $names[] = basename($path);
            }
        }

        return $names;
    }

    private function removeUploads(): void
    {
        foreach (glob(FCPATH . 'uploads/blocks/*') ?: [] as $path) {
            if (is_file($path) && preg_match('/^[a-f0-9]{32}\./', basename($path)) === 1) {
                unlink($path);
            }
        }

        foreach (glob(WRITEPATH . 'tests/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
