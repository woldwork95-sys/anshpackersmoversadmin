<?php

use App\Libraries\AdminAuthenticator;
use App\Models\AdminModel;
use App\Models\BlockModel;
use App\Models\CategoryModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AdminContentTest extends CIUnitTestCase
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

    public function testGuestCannotChangeCategoriesOrBlocks(): void
    {
        $categoryId = $this->createCategory('Kept category', 'kept-category');
        $blockId    = $this->createBlock($categoryId, 'Kept block', 'kept-block');

        $pages = [
            '/admin/categories',
            '/admin/categories/create',
            '/admin/categories/' . $categoryId . '/edit',
            '/admin/categories/' . $categoryId . '/delete',
            '/admin/blocks',
            '/admin/blocks/create',
            '/admin/blocks/' . $blockId,
            '/admin/blocks/' . $blockId . '/edit',
            '/admin/blocks/' . $blockId . '/delete',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertRedirectTo(site_url('admin/login'));
        }

        $this->post('/admin/categories', $this->categoryFields('Hijack', 'hijack'));
        $this->post('/admin/categories/' . $categoryId, $this->categoryFields('Changed', 'changed'));
        $this->post('/admin/categories/' . $categoryId . '/delete', []);
        $this->post('/admin/blocks', $this->blockFields($categoryId, 'Hijack block', 'hijack-block'));
        $this->post('/admin/blocks/' . $blockId, $this->blockFields($categoryId, 'Changed block', 'changed-block'));
        $this->post('/admin/blocks/' . $blockId . '/delete', []);

        $category = $this->db->table('categories')->where('id', $categoryId)->get()->getRowArray();
        $block    = $this->db->table('blocks')->where('id', $blockId)->get()->getRowArray();
        $this->assertSame('Kept category', $category['name']);
        $this->assertSame('Kept block', $block['title']);
        $this->assertSame(1, $this->db->table('categories')->countAllResults());
        $this->assertSame(1, $this->db->table('blocks')->countAllResults());
    }

    public function testCreateCategoryValidatesAndGeneratesSlug(): void
    {
        $invalid = $this->postAdmin('/admin/categories', [
            'name'        => 'A',
            'slug'        => 'Bad Slug',
            'description' => '',
            'status'      => 'archived',
        ]);

        $invalid->assertRedirectTo(site_url('admin/categories/create'));
        $this->assertStringContainsString('Choose a valid status.', $this->errorText());
        $this->assertSame(0, $this->db->table('categories')->countAllResults());

        $created = $this->postAdmin('/admin/categories', $this->categoryFields('Local Move', '', 'City <b>jobs</b>', 'active'));
        $created->assertRedirectTo(site_url('admin/categories'));
        $created->assertSessionHas('success', 'Category saved.');

        $row = $this->db->table('categories')->get()->getRowArray();
        $this->assertSame('Local Move', $row['name']);
        $this->assertSame('local-move', $row['slug']);
        $this->assertSame('City <b>jobs</b>', $row['description']);
        $this->assertSame('active', $row['status']);
        $this->assertNotEmpty($row['created_at']);
        $this->assertNotEmpty($row['updated_at']);

        $again = $this->postAdmin('/admin/categories', $this->categoryFields('Local Move', '', '', 'active'));
        $again->assertRedirectTo(site_url('admin/categories/create'));
        $this->assertStringContainsString('That slug is already in use.', $this->errorText());
        $this->assertSame(1, $this->db->table('categories')->countAllResults());
    }

    public function testUpdateCategoryAndRejectDuplicateSlug(): void
    {
        $first  = $this->createCategory('Alpha team', 'alpha-team');
        $second = $this->createCategory('Beta team', 'beta-team');
        $before = $this->db->table('categories')->where('id', $first)->get()->getRowArray();

        $duplicate = $this->postAdmin('/admin/categories/' . $first, [
            'id'          => (string) $second,
            'name'        => 'Alpha renamed',
            'slug'        => 'beta-team',
            'description' => '<img src=x onerror=alert(1)>',
            'status'      => 'inactive',
        ]);
        $duplicate->assertRedirectTo(site_url('admin/categories/' . $first . '/edit'));
        $this->assertSame('alpha-team', $this->db->table('categories')->where('id', $first)->get()->getRowArray()['slug']);
        $this->assertSame('Beta team', $this->db->table('categories')->where('id', $second)->get()->getRowArray()['name']);

        $updated = $this->postAdmin('/admin/categories/' . $first, [
            'id'          => (string) $second,
            'name'        => 'Alpha renamed',
            'slug'        => 'alpha-team',
            'description' => '<img src=x onerror=alert(1)>',
            'status'      => 'inactive',
        ]);
        $updated->assertRedirectTo(site_url('admin/categories'));

        $after = $this->db->table('categories')->where('id', $first)->get()->getRowArray();
        $this->assertSame('Alpha renamed', $after['name']);
        $this->assertSame('alpha-team', $after['slug']);
        $this->assertSame('inactive', $after['status']);
        $this->assertSame($before['created_at'], $after['created_at']);
        $this->assertSame('Beta team', $this->db->table('categories')->where('id', $second)->get()->getRowArray()['name']);

        $edit = $this->asAdmin()->get('/admin/categories/' . $first . '/edit');
        $edit->assertOK();
        $edit->assertSee('&lt;img src=x onerror=alert(1)&gt;');
        $edit->assertDontSee('<img src=x onerror=alert(1)>');
        $edit->assertSee('Created');
        $edit->assertSee('Updated');
    }

    public function testDeleteCategoryRequiresConfirmationAndBlocksDependencies(): void
    {
        $categoryId = $this->createCategory('Packed goods', 'packed-goods');
        $blockId    = $this->createBlock($categoryId, 'Box list', 'box-list');

        $confirm = $this->asAdmin()->get('/admin/categories/' . $categoryId . '/delete');
        $confirm->assertOK();
        $confirm->assertSee('This category is used by 1 block and cannot be deleted.');
        $confirm->assertDontSee('btn-danger');
        $this->assertNotNull($this->db->table('categories')->where('id', $categoryId)->get()->getRowArray());

        $denied = $this->postAdmin('/admin/categories/' . $categoryId . '/delete', []);
        $denied->assertRedirectTo(site_url('admin/categories/' . $categoryId . '/delete'));
        $this->assertNotNull($this->db->table('categories')->where('id', $categoryId)->get()->getRowArray());

        $this->postAdmin('/admin/blocks/' . $blockId . '/delete', [])->assertRedirectTo(site_url('admin/blocks'));
        $this->assertNull($this->db->table('blocks')->where('id', $blockId)->get()->getRowArray());

        $open = $this->asAdmin()->get('/admin/categories/' . $categoryId . '/delete');
        $open->assertSee('Delete the category');
        $open->assertSee('Delete category');
        $this->postAdmin('/admin/categories/' . $categoryId . '/delete', [])
            ->assertRedirectTo(site_url('admin/categories'));
        $this->assertNull($this->db->table('categories')->where('id', $categoryId)->get()->getRowArray());
    }

    public function testDatabaseRejectsDeletingACategoryThatStillHasBlocks(): void
    {
        $categoryId = $this->createCategory('Locked', 'locked');
        $this->createBlock($categoryId, 'Still here', 'still-here');

        $this->expectException(DatabaseException::class);
        $this->db->table('categories')->where('id', $categoryId)->delete();
    }

    public function testCreateAndUpdateBlock(): void
    {
        $active   = $this->createCategory('Local', 'local');
        $inactive = $this->createCategory('Archive', 'archive', 'inactive');

        $missing = $this->postAdmin('/admin/blocks', $this->blockFields(0, 'No category', 'no-category'));
        $missing->assertRedirectTo(site_url('admin/blocks/create'));
        $this->assertStringContainsString('Choose a valid category.', $this->errorText());

        foreach (['99999', 'abc', '0', '1 OR 1=1'] as $badId) {
            $rejected = $this->postAdmin('/admin/blocks', $this->blockFields($badId, 'Bad category', 'bad-category'));
            $rejected->assertRedirectTo(site_url('admin/blocks/create'));
            $this->assertStringContainsString('Choose a valid category.', $this->errorText());
        }

        $badStatus = $this->postAdmin('/admin/blocks', array_merge(
            $this->blockFields($active, 'Bad status', 'bad-status'),
            ['status' => 'archived'],
        ));
        $badStatus->assertRedirectTo(site_url('admin/blocks/create'));
        $this->assertStringContainsString('Choose a valid status.', $this->errorText());
        $this->assertSame(0, $this->db->table('blocks')->countAllResults());

        $created = $this->postAdmin('/admin/blocks', $this->blockFields(
            $inactive,
            'Kitchen pack',
            '',
            "First line\n<script>alert(1)</script>",
            'draft',
        ));
        $created->assertRedirectTo(site_url('admin/blocks'));
        $created->assertSessionHas('success', 'Post saved.');

        $row = $this->db->table('blocks')->get()->getRowArray();
        $this->assertSame('Kitchen pack', $row['title']);
        $this->assertSame('kitchen-pack', $row['slug']);
        $this->assertSame($inactive, (int) $row['category_id']);
        $this->assertSame('draft', $row['status']);
        $this->assertStringContainsString('First line', $row['content']);
        $this->assertStringNotContainsString('<script', strtolower((string) $row['content']));
        $this->assertNotEmpty($row['created_at']);
        $before = $row['created_at'];
        $id     = (int) $row['id'];

        $duplicate = $this->postAdmin('/admin/blocks', $this->blockFields($active, 'Other', 'kitchen-pack'));
        $duplicate->assertRedirectTo(site_url('admin/blocks/create'));
        $this->assertStringContainsString('That slug is already in use.', $this->errorText());
        $this->assertSame(1, $this->db->table('blocks')->countAllResults());

        $updated = $this->postAdmin('/admin/blocks/' . $id, array_merge(
            $this->blockFields($active, 'Kitchen route', 'kitchen-route', 'Packed text', 'published'),
            ['id' => '999'],
        ));
        $updated->assertRedirectTo(site_url('admin/blocks'));

        $after = $this->db->table('blocks')->where('id', $id)->get()->getRowArray();
        $this->assertSame('Kitchen route', $after['title']);
        $this->assertSame('kitchen-route', $after['slug']);
        $this->assertSame($active, (int) $after['category_id']);
        $this->assertSame('published', $after['status']);
        $this->assertSame($before, $after['created_at']);
        $this->assertNotEmpty($after['updated_at']);

        $preview = $this->asAdmin()->get('/admin/blocks/' . $id);
        $preview->assertOK();
        $preview->assertSee('Kitchen route');
        $preview->assertSee('Local');
        $preview->assertSee('published');
        $preview->assertSee('Packed text');
        $preview->assertSee('Created');
        $preview->assertSee('Updated');
        $preview->assertDontSee('<textarea');
        $preview->assertDontSee('tinymce');
    }

    public function testBlockPreviewEscapesContent(): void
    {
        $categoryId = $this->createCategory('Local', 'local');
        $blockId    = $this->createBlock($categoryId, '<script>alert(1)</script>', 'alert-title', "<script>alert(1)</script>");

        $preview = $this->asAdmin()->get('/admin/blocks/' . $blockId);
        $preview->assertOK();
        $preview->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;');
        $preview->assertDontSee('<script>alert(1)</script>');
    }

    public function testSearchFiltersAndPagination(): void
    {
        $local  = $this->createCategory('Local', 'local');
        $office = $this->createCategory('Office', 'office');
        $this->createBlock($local, 'Kitchen pack', 'kitchen-pack', 'notes', 'published');
        $this->createBlock($office, 'Safe cargo', 'safe-cargo', 'notes', 'draft');

        $byTitle = $this->asAdmin()->get('/admin/blocks?q=Kitchen');
        $byTitle->assertOK();
        $byTitle->assertSee('Kitchen pack');
        $byTitle->assertDontSee('Safe cargo');

        $bySlug = $this->asAdmin()->get('/admin/blocks?q=safe-cargo');
        $bySlug->assertOK();
        $bySlug->assertSee('Safe cargo');
        $bySlug->assertDontSee('Kitchen pack');

        $filtered = $this->asAdmin()->get('/admin/blocks?category=' . $local . '&status=published');
        $filtered->assertOK();
        $filtered->assertSee('Kitchen pack');
        $filtered->assertDontSee('Safe cargo');

        $unknown = $this->asAdmin()->get('/admin/blocks?category=99999&status=published');
        $unknown->assertOK();
        $unknown->assertSee('No posts match these filters.');
        $unknown->assertDontSee('Kitchen pack');

        $ignored = $this->asAdmin()->get('/admin/blocks?status=archived&category=1%20OR%201=1&q=' . rawurlencode("%' OR 1=1 --"));
        $ignored->assertOK();
        $ignored->assertDontSee('Kitchen pack');
        $ignored->assertDontSee('Safe cargo');

        for ($i = 1; $i <= 11; $i++) {
            $name = sprintf('Item %02d', $i);
            $this->createCategory($name, 'item-' . sprintf('%02d', $i));
        }

        $page = $this->asAdmin()->get('/admin/categories?q=Item');
        $page->assertOK();
        $page->assertSee('Item 01');
        $page->assertSee('Item 10');
        $page->assertDontSee('Item 11');
        $page->assertSee('page=2');

        Services::resetSingle('pager');
        $next = $this->asAdmin()->get('/admin/categories?q=Item&page=2');
        $next->assertOK();
        $next->assertSee('Item 11');
        $next->assertDontSee('Item 01');

        for ($i = 1; $i <= 11; $i++) {
            $this->createBlock($local, sprintf('Cargo %02d', $i), sprintf('cargo-%02d', $i));
        }

        Services::resetSingle('pager');
        $blocks = $this->asAdmin()->get('/admin/blocks?q=Cargo');
        $blocks->assertOK();
        $blocks->assertSee('Cargo 11');
        $blocks->assertDontSee('Cargo 01');

        Services::resetSingle('pager');
        $second = $this->asAdmin()->get('/admin/blocks?q=Cargo&page=2');
        $second->assertOK();
        $second->assertSee('Cargo 01');
        $second->assertDontSee('Cargo 11');
    }

    public function testMissingRecordsAndMissingCsrfDoNotChangeData(): void
    {
        $categoryId = $this->createCategory('Local', 'local');
        $this->createBlock($categoryId, 'Box', 'box');

        $this->assertPageMissing(fn () => $this->asAdmin()->get('/admin/categories/999/edit'));
        $this->assertPageMissing(fn () => $this->asAdmin()->get('/admin/blocks/999'));
        $this->assertPageMissing(fn () => $this->postAdmin('/admin/categories/999', $this->categoryFields('Nope', 'nope')));
        $this->assertSame(0, $this->db->table('categories')->where('slug', 'nope')->countAllResults());

        Services::resetSingle('security');
        $csrf = $this->withSession([
            AdminAuthenticator::FLAG     => true,
            AdminAuthenticator::ID       => $this->adminId,
            AdminAuthenticator::USERNAME => 'admin',
        ])->post('/admin/categories', $this->categoryFields('No token', 'no-token'));

        $csrf->assertSessionHas('error', 'The action you requested is not allowed.');
        $this->assertSame(0, $this->db->table('categories')->where('slug', 'no-token')->countAllResults());
        $this->assertSame('Local', $this->db->table('categories')->where('id', $categoryId)->get()->getRowArray()['name']);
    }

    public function testCategorySearch(): void
    {
        $this->createCategory('Local shifting', 'local-shifting');
        $this->createCategory('Office move', 'office-move');

        $result = $this->asAdmin()->get('/admin/categories?q=office-move');
        $result->assertOK();
        $result->assertSee('Office move');
        $result->assertDontSee('Local shifting');
    }

    /**
     * @param callable(): mixed $request
     */
    private function assertPageMissing(callable $request): void
    {
        try {
            $request();
            $this->fail('A missing admin record should not be found.');
        } catch (PageNotFoundException) {
            $this->assertTrue(true);
        }
    }

    /**
     * @param array<string, string> $fields
     */
    private function postAdmin(string $path, array $fields): \CodeIgniter\Test\TestResponse
    {
        Services::resetSingle('security');

        return $this->asAdmin()->post($path, array_merge([
            'csrf_test_name' => $this->csrfHash,
        ], $fields));
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

    private function errorText(): string
    {
        $errors = session('errors');
        if (is_array($errors)) {
            return implode(' ', array_map(strval(...), $errors));
        }

        return (string) (session('error') ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function categoryFields(string $name, string $slug, string $description = '', string $status = 'active'): array
    {
        return [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'status'      => $status,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function blockFields(int|string $categoryId, string $title, string $slug, string $content = 'Plain text', string $status = 'draft'): array
    {
        return [
            'title'       => $title,
            'slug'        => $slug,
            'content'     => $content,
            'status'      => $status,
            'category_id' => (string) $categoryId,
        ];
    }

    private function createCategory(string $name, string $slug, string $status = 'active'): int
    {
        return (int) model(CategoryModel::class, false)->insert([
            'name'   => $name,
            'slug'   => $slug,
            'status' => $status,
        ]);
    }

    private function createBlock(int $categoryId, string $title, string $slug, string $content = 'Plain text', string $status = 'draft'): int
    {
        return (int) model(BlockModel::class, false)->insert([
            'category_id' => $categoryId,
            'title'       => $title,
            'slug'        => $slug,
            'content'     => $content,
            'status'      => $status,
        ]);
    }
}
