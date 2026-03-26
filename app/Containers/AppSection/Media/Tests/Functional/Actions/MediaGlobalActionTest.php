<?php

namespace App\Containers\AppSection\Media\Tests\Functional\Actions;

use App\Containers\AppSection\Media\Actions\MediaGlobalActionAction;
use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Containers\AppSection\Media\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MediaGlobalActionAction::class)]
final class MediaGlobalActionTest extends ApiTestCase
{
    private MediaGlobalActionAction $action;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(MediaGlobalActionAction::class);
        $this->user = User::factory()->superAdmin()->createOne();
        Storage::fake('public');
    }

    public function testTrashAndRestoreAction(): void
    {
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id,
            'name' => 'To Trash',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'test.jpg',
            'folder_id' => 0,
        ]);

        // Trash
        $this->action->run('trash', ['selected' => [['id' => $file->id, 'is_folder' => false]]], $this->user->id);
        
        $this->assertSoftDeleted('media_files', ['id' => $file->id]);

        // Restore
        $this->action->run('restore', ['selected' => [['id' => $file->id, 'is_folder' => false]]], $this->user->id);
        
        $this->assertDatabaseHas('media_files', ['id' => $file->id, 'deleted_at' => null]);
    }

    public function testMoveAction(): void
    {
        $folder = MediaFolder::query()->create(['user_id' => $this->user->id, 'name' => 'Target Folder']);
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id, 
            'folder_id' => 0, 
            'name' => 'To Move',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'move.jpg',
        ]);

        $this->action->run('move', [
            'selected' => [['id' => $file->id, 'is_folder' => false]],
            'destination' => $folder->id,
        ], $this->user->id);

        $this->assertDatabaseHas('media_files', [
            'id' => $file->id,
            'folder_id' => $folder->id,
        ]);
    }

    public function testFavoriteActions(): void
    {
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Favorite File',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'fav.jpg',
            'folder_id' => 0,
        ]);

        // Add Favorite
        $this->action->run('favorite', ['selected' => [['id' => $file->id, 'is_folder' => false]]], $this->user->id);

        $service = app(\App\Containers\AppSection\Media\Services\MediaService::class);
        $favorites = $service->getFavoriteItems($this->user->id);
        
        $this->assertNotEmpty($favorites);
        $this->assertEquals($file->id, $favorites[0]['id']);

        // Remove Favorite
        $this->action->run('remove_favorite', ['selected' => [['id' => $file->id, 'is_folder' => false]]], $this->user->id);
        
        $service->forgetUserItemsCache($this->user->id); // Ensure cache is cleared (handler does this, but to trigger re-fetch in test)
        
        $favorites = $service->getFavoriteItems($this->user->id);
        $this->assertEmpty($favorites);
    }

    public function testMakeCopyAction(): void
    {
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Original File',
            'url' => 'original.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'folder_id' => 0,
        ]);
        Storage::disk('public')->put('original.jpg', 'content');

        $this->action->run('make_copy', [
            'selected' => [['id' => $file->id, 'is_folder' => false]],
        ], $this->user->id);

        // Should have 2 files now
        $this->assertEquals(2, MediaFile::count());
        $copy = MediaFile::where('id', '!=', $file->id)->first();
        
        $this->assertStringContainsString('Original File', $copy->name);
    }

    public function testDeleteActionPermanently(): void
    {
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id,
            'name' => 'To Delete',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'del.jpg',
            'folder_id' => 0,
        ]);

        $this->action->run('delete', ['selected' => [['id' => $file->id, 'is_folder' => false]]], $this->user->id);

        $this->assertDatabaseMissing('media_files', ['id' => $file->id]);
    }

    public function testEmptyTrashAction(): void
    {
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Trash File',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'trash.jpg',
            'folder_id' => 0,
        ]);
        $file->delete(); // Soft delete

        $this->action->run('empty_trash', [], $this->user->id);

        $this->assertDatabaseMissing('media_files', ['id' => $file->id]);
    }

    public function testRenameAction(): void
    {
        $file = MediaFile::query()->create([
            'user_id' => $this->user->id, 
            'name' => 'Old Name',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'rename.jpg',
            'folder_id' => 0,
        ]);

        $this->action->run('rename', [
            'selected' => [['id' => $file->id, 'is_folder' => false, 'name' => 'New Name']],
        ], $this->user->id);

        $this->assertDatabaseHas('media_files', ['id' => $file->id, 'name' => 'New Name']);
    }
}
