<?php

use App\Libraries\AdminAuthenticator;
use App\Models\AdminModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ContentDataFileTest extends CIUnitTestCase
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

    public function testFixtureDataIsStoredAndReturnedByTheSingleApi(): void
    {
        $data = $this->fixture();

        foreach ($data['rejected'] as $case) {
            if ($case['type'] !== 'category') {
                continue;
            }

            $this->assertGreaterThan($case['limit'], strlen($case['fields']['meta_description']));
            $this->postAdmin('/admin/categories', $case['fields'])
                ->assertRedirectTo(site_url('admin/categories/create'));
            $this->assertSame(0, $this->db->table('categories')->where('slug', $case['fields']['slug'])->countAllResults());
        }

        $categoryIds = [];
        foreach ($data['categories'] as $case) {
            $this->postAdmin('/admin/categories', $case['fields'])
                ->assertRedirectTo(site_url('admin/categories'));

            $row = $this->db->table('categories')->where('slug', $case['fields']['slug'])->get()->getRowArray();
            $this->assertIsArray($row, $case['fields']['slug']);
            foreach ($case['stored'] as $field => $expected) {
                $this->assertSame($expected, $row[$field], 'category ' . $case['fields']['slug'] . ' ' . $field);
            }
            $categoryIds[$case['fields']['slug']] = (int) $row['id'];
        }

        foreach ($data['rejected'] as $case) {
            if ($case['type'] !== 'post') {
                continue;
            }

            $this->assertGreaterThan($case['limit'], strlen($case['fields']['meta_description']));
            $fields                = $case['fields'];
            $fields['category_id'] = (string) $categoryIds[$case['category']];
            $this->postAdmin('/admin/blocks', $fields)
                ->assertRedirectTo(site_url('admin/blocks/create'));
            $this->assertSame(0, $this->db->table('blocks')->where('slug', $case['fields']['slug'])->countAllResults());
        }

        foreach ($data['posts'] as $case) {
            $fields                = $case['fields'];
            $fields['category_id'] = (string) $categoryIds[$case['category']];
            $this->postAdmin('/admin/blocks', $fields)
                ->assertRedirectTo(site_url('admin/blocks'));

            $row = $this->db->table('blocks')->where('slug', $case['fields']['slug'])->get()->getRowArray();
            $this->assertIsArray($row, $case['fields']['slug']);
            $this->assertSame($categoryIds[$case['category']], (int) $row['category_id'], $case['fields']['slug'] . ' category_id');
            foreach ($case['stored'] as $field => $expected) {
                $this->assertSame($expected, $row[$field], 'post ' . $case['fields']['slug'] . ' ' . $field);
            }
            if ($case['role'] === 'published' || $case['role'] === 'published-on-inactive') {
                $this->assertNotEmpty($row['published_at'], $case['fields']['slug'] . ' published_at');
            }
        }

        $payload    = $this->json($this->get('/api'));
        $categories = [];
        foreach ($payload['data']['categories'] as $category) {
            $categories[$category['slug']] = $category;
        }
        $posts = [];
        foreach ($payload['data']['posts'] as $post) {
            $posts[$post['slug']] = $post;
        }

        $this->assertSame(['city-moves'], array_keys($categories));
        $this->assertEqualsCanonicalizing(['packing-checklist', 'closed-route-note'], array_keys($posts));
        $encoded = json_encode($payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('hidden-draft', $encoded);
        $this->assertStringNotContainsString('old-routes', $encoded);
        $this->assertStringNotContainsString('too-long', $encoded);
        $this->assertStringNotContainsString('<script', strtolower($encoded));

        $active = $data['categories'][0]['api'];
        $this->assertSame($active['name'], $categories['city-moves']['name']);
        $this->assertSame($active['description'], $categories['city-moves']['description']);
        $this->assertSame($active['status'], $categories['city-moves']['status']);
        foreach ($active['seo'] as $field => $expected) {
            $this->assertSame($expected, $categories['city-moves']['seo'][$field], 'api category seo ' . $field);
        }
        $this->assertSame(['packing-checklist'], array_column($categories['city-moves']['posts'], 'slug'));
        $this->assertArrayNotHasKey('content', $categories['city-moves']['posts'][0]);

        foreach ($data['posts'] as $case) {
            if (! isset($case['api'])) {
                continue;
            }

            $post = $posts[$case['api']['slug']];
            foreach (['title', 'slug', 'excerpt', 'author', 'status', 'content'] as $field) {
                $this->assertSame($case['api'][$field], $post[$field], 'api post ' . $case['api']['slug'] . ' ' . $field);
            }
            $this->assertSame($case['api']['category'], $post['category'], 'api post ' . $case['api']['slug'] . ' category');
            $this->assertNotSame('', $post['url'], $case['api']['slug'] . ' url');
            $this->assertStringContainsString('/blog/' . $case['api']['slug'], $post['url']);
            if (isset($case['api']['seo'])) {
                foreach ($case['api']['seo'] as $field => $expected) {
                    $this->assertSame($expected, $post['seo'][$field], 'api post seo ' . $field);
                }
                $this->assertSame($post['url'], $post['seo']['canonical']);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        $path = SUPPORTPATH . 'fixtures/blog-content.json';
        $raw  = file_get_contents($path);
        $this->assertIsString($raw);
        $data = json_decode($raw, true);
        $this->assertIsArray($data);

        return $data;
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
