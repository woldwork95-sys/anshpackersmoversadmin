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
final class ServiceContentTest extends CIUnitTestCase
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

    public function testPublishedServiceIsStoredAndReturned(): void
    {
        $created = $this->postAdmin('/admin/services', [
            'title'            => 'Packing service',
            'slug'             => 'packing-service',
            'excerpt'          => 'Card summary',
            'image_alt'        => 'Packed cartons',
            'sort_order'       => '4',
            'content'          => '<p><strong>Packed</strong> with care</p><script>alert(1)</script>',
            'status'           => 'published',
            'meta_title'       => 'Packing meta title',
            'meta_description' => 'Packing meta description for the service.',
            'robots_index'     => 'index',
            'robots_follow'    => 'follow',
            'og_title'         => 'Packing share title',
            'og_description'   => 'Packing share description.',
        ]);
        $created->assertRedirectTo(site_url('admin/services'));

        $row = $this->db->table('services')->where('slug', 'packing-service')->get()->getRowArray();
        $this->assertSame('Packing service', $row['title']);
        $this->assertSame('packing-service', $row['slug']);
        $this->assertSame('Card summary', $row['excerpt']);
        $this->assertSame('Packed cartons', $row['image_alt']);
        $this->assertSame(4, (int) $row['sort_order']);
        $this->assertSame('published', $row['status']);
        $this->assertNotEmpty($row['published_at']);
        $this->assertStringContainsString('<strong>Packed</strong>', (string) $row['content']);
        $this->assertStringNotContainsString('<script', strtolower((string) $row['content']));

        $draft = $this->postAdmin('/admin/services', [
            'title'   => 'Hidden service',
            'slug'    => 'hidden-service',
            'content' => 'Not public',
            'status'  => 'draft',
        ]);
        $draft->assertRedirectTo(site_url('admin/services'));

        $payload = $this->json($this->get('/api'));
        $this->assertCount(1, $payload['data']['services']);
        $service = $payload['data']['services'][0];
        $this->assertSame('Packing service', $service['title']);
        $this->assertSame('packing-service', $service['slug']);
        $this->assertSame('Card summary', $service['excerpt']);
        $this->assertSame('Packed cartons', $service['image_alt']);
        $this->assertSame(4, $service['sort_order']);
        $this->assertSame('published', $service['status']);
        $this->assertStringContainsString('<strong>Packed</strong>', $service['content']);
        $this->assertStringNotContainsString('<script', strtolower($service['content']));
        $this->assertSame('Packing meta title', $service['seo']['title']);
        $this->assertSame('Packing meta description for the service.', $service['seo']['description']);
        $this->assertSame('index, follow', $service['seo']['robots']);
        $this->assertStringNotContainsString('hidden-service', json_encode($payload));

        $id = (int) $row['id'];
        $renamed = $this->postAdmin('/admin/services/' . $id, [
            'title'      => 'Packing service',
            'slug'       => 'packing-renamed',
            'excerpt'    => 'Card summary',
            'sort_order' => '4',
            'content'    => '<p>Packed</p>',
            'status'     => 'published',
        ]);
        $renamed->assertRedirectTo(site_url('admin/services'));

        $after = $this->json($this->get('/api'));
        $this->assertSame('packing-renamed', $after['data']['services'][0]['slug']);
        $this->assertSame('packing-renamed', $after['data']['service_redirects']['packing-service']);
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
