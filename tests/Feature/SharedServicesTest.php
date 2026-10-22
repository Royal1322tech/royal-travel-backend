<?php

namespace Tests\Feature;

use App\Core\Services\FileUploadService;
use App\Core\Services\PaginationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class SharedServicesTest extends TestCase
{
    public function test_upload_and_delete_private_file(): void
    {
        Storage::fake('local');
        $service = new FileUploadService();
        $path = $service->upload(UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), 'companies/1/documents');
        Storage::disk('local')->assertExists($path);
        $this->assertTrue($service->delete($path));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_rejects_directory_traversal(): void
    {
        Storage::fake('local');
        $this->expectException(InvalidArgumentException::class);
        (new FileUploadService())->upload(UploadedFile::fake()->create('a.pdf'), '../outside');
    }

    public function test_pagination_clamps_page_size(): void
    {
        $service = new PaginationService();
        $this->assertSame(100, $service->perPage(Request::create('/?per_page=999', 'GET')));
        $this->assertSame(15, $service->perPage(Request::create('/?per_page=invalid', 'GET')));
        $this->assertSame(1, $service->perPage(Request::create('/?per_page=1', 'GET')));
    }
}
