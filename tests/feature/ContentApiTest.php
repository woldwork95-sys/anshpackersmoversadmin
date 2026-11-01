<?php

use App\Libraries\AdminAuthenticator;
use App\Libraries\BlockSeo;
use App\Models\AdminModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ContentApiTest extends CIUnitTestCase
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

    public function testAdminOrderAndBlogActionsSitOnTheRight(): void
    {
        $home = preg_replace('/\s+/', '', (string) $this->asAdmin()->get('/admin')->getBody()) ?? '';
        $this->assertLessThan(strpos($home, '>Blog<'), strpos($home, '>Categories<'));

        $form = (string) $this->asAdmin()->get('/admin/blocks/create')->getBody();
        $this->assertStringContainsString('form-card-editor', $form);
        $this->assertSame(1, preg_match('/<div class="editor-layout">[\s\S]*<div class="editor-main">[\s\S]*data-block-editor[\s\S]*<div class="publish-bar">[\s\S]*id="block-status"[\s\S]*Save draft[\s\S]*Publish/', $form));

        $css = file_get_contents(FCPATH . 'assets/admin/admin.css');
        $this->assertIsString($css);
        $this->assertStringContainsString('.form-card-editor { max-width: none; width: 100%; }', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) 17.5rem', $css);
        $this->assertStringContainsString('.ck.ck-editor { width: 100%; max-width: none; }', $css);
        $this->assertStringContainsString(".publish-bar {\n    position: sticky;", $css);
        $this->assertDoesNotMatchRegularExpression('/\.publish-bar \{[^}]*position:\s*fixed/', $css);

        $category = (string) $this->asAdmin()->get('/admin/categories/create')->getBody();
        $this->assertStringContainsString('Search appearance', $category);
        $this->assertStringContainsString('name="meta_title"', $category);
        $this->assertStringContainsString('name="meta_description"', $category);
        $this->assertStringContainsString('name="og_title"', $category);
        $this->assertStringContainsString('name="og_description"', $category);
    }

    public function testEachCategoryFieldIsStoredAndReturned(): void
    {
        $tooLong = $this->postAdmin('/admin/categories', [
            'name'             => 'City moves',
            'slug'             => 'city-moves',
            'description'      => 'Short',
            'status'           => 'active',
            'meta_description' => str_repeat('x', BlockSeo::DESCRIPTION_LIMIT + 1),
        ]);
        $tooLong->assertRedirectTo(site_url('admin/categories/create'));
        $this->assertSame(0, $this->db->table('categories')->countAllResults());

        $created = $this->postAdmin('/admin/categories', [
            'name'             => 'City moves',
            'slug'             => 'city-moves',
            'description'      => 'Category <b>description</b>',
            'status'           => 'active',
            'meta_title'       => 'City meta title',
            'meta_description' => 'City meta description for search.',
            'robots_index'     => 'noindex',
            'robots_follow'    => 'nofollow',
            'og_title'         => 'City share title',
            'og_description'   => 'City share description.',
        ]);
        $created->assertRedirectTo(site_url('admin/categories'));

        $row = $this->db->table('categories')->where('slug', 'city-moves')->get()->getRowArray();
        $this->assertSame('City moves', $row['name']);
        $this->assertSame('city-moves', $row['slug']);
        $this->assertSame('Category <b>description</b>', $row['description']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('City meta title', $row['meta_title']);
        $this->assertSame('City meta description for search.', $row['meta_description']);
        $this->assertSame('noindex', $row['robots_index']);
        $this->assertSame('nofollow', $row['robots_follow']);
        $this->assertSame('City share title', $row['og_title']);
        $this->assertSame('City share description.', $row['og_description']);

        $payload = $this->json($this->get('/api'));
        $this->assertCount(1, $payload['data']['categories']);
        $this->assertSame([], $payload['data']['posts']);
        $item = $payload['data']['categories'][0];
        $this->assertSame('City moves', $item['name']);
        $this->assertSame('city-moves', $item['slug']);
        $this->assertSame('Category description', $item['description']);
        $this->assertSame('active', $item['status']);
        $this->assertSame('City meta title', $item['seo']['title']);
        $this->assertSame('City meta description for search.', $item['seo']['description']);
        $this->assertSame('noindex, nofollow', $item['seo']['robots']);
        $this->assertSame('City share title', $item['seo']['og_title']);
        $this->assertSame('City share description.', $item['seo']['og_description']);
        $this->assertSame([], $item['posts']);
    }

    public function testEachPostFieldIsStoredAndReturned(): void
    {
        $createdCategory = $this->postAdmin('/admin/categories', [
            'name'   => 'Local shifting',
            'slug'   => 'local-shifting',
            'status' => 'active',
        ]);
        $createdCategory->assertRedirectTo(site_url('admin/categories'));
        $categoryId = (int) $this->db->table('categories')->where('slug', 'local-shifting')->get()->getRowArray()['id'];

        $content = '<p><strong>Bold line</strong></p><script>alert(1)</script>';
        $created = $this->postAdmin('/admin/blocks', [
            'title'            => 'Packing checklist',
            'slug'             => 'packing-checklist',
            'category_id'      => (string) $categoryId,
            'author_name'      => 'Neha',
            'excerpt'          => 'Checklist excerpt',
            'content'          => $content,
            'status'           => 'published',
            'meta_title'       => 'Packing meta title',
            'meta_description' => 'Packing meta description for the post.',
            'robots_index'     => 'index',
            'robots_follow'    => 'follow',
            'og_title'         => 'Packing share title',
            'og_description'   => 'Packing share description.',
        ]);
        $created->assertRedirectTo(site_url('admin/blocks'));

        $row = $this->db->table('blocks')->where('slug', 'packing-checklist')->get()->getRowArray();
        $this->assertSame('Packing checklist', $row['title']);
        $this->assertSame('packing-checklist', $row['slug']);
        $this->assertSame($categoryId, (int) $row['category_id']);
        $this->assertSame('Neha', $row['author_name']);
        $this->assertSame('Checklist excerpt', $row['excerpt']);
        $this->assertStringContainsString('<strong>Bold line</strong>', (string) $row['content']);
        $this->assertStringNotContainsString('<script', strtolower((string) $row['content']));
        $this->assertSame('published', $row['status']);
        $this->assertNotEmpty($row['published_at']);
        $this->assertSame('Packing meta title', $row['meta_title']);
        $this->assertSame('Packing meta description for the post.', $row['meta_description']);
        $this->assertSame('index', $row['robots_index']);
        $this->assertSame('follow', $row['robots_follow']);
        $this->assertSame('Packing share title', $row['og_title']);
        $this->assertSame('Packing share description.', $row['og_description']);

        $draft = $this->postAdmin('/admin/blocks', [
            'title'       => 'Hidden draft',
            'slug'        => 'hidden-draft',
            'category_id' => (string) $categoryId,
            'content'     => 'Not public',
            'status'      => 'draft',
        ]);
        $draft->assertRedirectTo(site_url('admin/blocks'));

        $payload = $this->json($this->get('/api'));
        $this->assertCount(1, $payload['data']['posts']);
        $this->assertCount(1, $payload['data']['categories']);
        $data = $payload['data']['posts'][0];
        $this->assertSame('Packing checklist', $data['title']);
        $this->assertSame('packing-checklist', $data['slug']);
        $this->assertSame('Checklist excerpt', $data['excerpt']);
        $this->assertSame('Neha', $data['author']);
        $this->assertSame('published', $data['status']);
        $this->assertSame('Local shifting', $data['category']['name']);
        $this->assertSame('local-shifting', $data['category']['slug']);
        $this->assertNotSame('', $data['url']);
        $this->assertStringContainsString('<strong>Bold line</strong>', $data['content']);
        $this->assertStringNotContainsString('<script', strtolower($data['content']));
        $this->assertSame('Packing meta title', $data['seo']['title']);
        $this->assertSame('Packing meta description for the post.', $data['seo']['description']);
        $this->assertSame('index, follow', $data['seo']['robots']);
        $this->assertSame('Packing share title', $data['seo']['og_title']);
        $this->assertSame('Packing share description.', $data['seo']['og_description']);
        $this->assertStringNotContainsString('hidden-draft', json_encode($payload));

        $grouped = $payload['data']['categories'][0];
        $this->assertSame('local-shifting', $grouped['slug']);
        $this->assertSame('packing-checklist', $grouped['posts'][0]['slug']);
        $this->assertArrayNotHasKey('content', $grouped['posts'][0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(\CodeIgniter\Test\TestResponse $response): array
    {
        $response->assertOK();
        $decoded = json_decode($response->response()->getBody(), true);
        $this->assertIsArray($decoded);

        return $decoded;
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
}
