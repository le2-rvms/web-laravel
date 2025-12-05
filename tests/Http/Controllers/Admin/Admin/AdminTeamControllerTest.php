<?php

namespace Tests\Http\Controllers\Admin\Admin;

use App\Enum\Admin\AtAtStatus;
use App\Http\Controllers\Admin\Admin\AdminTeamController;
use App\Models\Admin\AdminTeam;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class AdminTeamControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AdminTeam::query()->delete();
    }

    public function testIndexReturnsPaginatedTeamsWithExtras(): void
    {
        AdminTeam::query()->create([
            'at_id'        => 1,
            'at_parent_id' => null,
            'at_name'      => 'Alpha',
            'at_status'    => AtAtStatus::ENABLED,
            'at_sort'      => 1,
        ]);

        AdminTeam::query()->create([
            'at_id'        => 2,
            'at_parent_id' => null,
            'at_name'      => 'Beta',
            'at_status'    => AtAtStatus::DISABLED,
        ]);

        $response = $this->getJson(action([AdminTeamController::class, 'index']));

        $response->assertOk()
            ->assertJsonStructure([
                'data'  => ['data'],
                'extra' => ['AtAtStatusOptions'],
                'option',
            ])
        ;

        $this->assertIsArray($response->json('data.data'));
        $this->assertCount(2, $response->json('data.data'));
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $response = $this->postJson(action([AdminTeamController::class, 'store']), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['at_name', 'at_status'])
        ;
    }

    public function testStoreCreatesTeam(): void
    {
        $payload = [
            'at_parent_id' => null,
            'at_name'      => 'North Team',
            'at_status'    => AtAtStatus::ENABLED,
            'at_sort'      => 10,
            'at_remark'    => 'Primary',
        ];

        $response = $this->postJson(action([AdminTeamController::class, 'store']), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('admin_teams', [
            'at_name'   => 'North Team',
            'at_status' => AtAtStatus::ENABLED,
            'at_sort'   => 10,
        ]);
    }

    public function testUpdateValidatesNameUniqueness(): void
    {
        $first = AdminTeam::query()->create([
            'at_id'     => 1,
            'at_name'   => 'Alpha',
            'at_status' => AtAtStatus::ENABLED,
        ]);

        $second = AdminTeam::query()->create([
            'at_id'     => 2,
            'at_name'   => 'Bravo',
            'at_status' => AtAtStatus::ENABLED,
        ]);

        $response = $this->putJson(
            action([AdminTeamController::class, 'update'], ['admin_team' => $second]),
            [
                'at_name'   => $first->at_name,
                'at_status' => AtAtStatus::ENABLED,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['at_name'])
        ;
    }

    public function testUpdatePersistsChanges(): void
    {
        $team = AdminTeam::query()->create([
            'at_id'     => 3,
            'at_name'   => 'Gamma',
            'at_status' => AtAtStatus::DISABLED,
            'at_sort'   => 3,
            'at_remark' => 'Old',
        ]);

        $response = $this->putJson(
            action([AdminTeamController::class, 'update'], ['admin_team' => $team]),
            [
                'at_parent_id' => null,
                'at_name'      => 'Gamma Updated',
                'at_status'    => AtAtStatus::ENABLED,
                'at_sort'      => 5,
                'at_remark'    => 'Updated remark',
            ]
        );

        $response->assertOk();

        $team->refresh();
        $this->assertSame('Gamma Updated', $team->at_name);
        $this->assertSame(AtAtStatus::ENABLED, $team->at_status);
        $this->assertSame(5, $team->at_sort);
        $this->assertSame('Updated remark', $team->at_remark);
    }

    public function testShowAndDestroyLifecycle(): void
    {
        $team = AdminTeam::query()->create([
            'at_id'     => 4,
            'at_name'   => 'Delta',
            'at_status' => AtAtStatus::ENABLED,
        ]);

        $show = $this->getJson(action([AdminTeamController::class, 'show'], ['admin_team' => $team]));
        $show->assertOk()
            ->assertJsonFragment(['at_name' => 'Delta'])
        ;

        $delete = $this->deleteJson(action([AdminTeamController::class, 'destroy'], ['admin_team' => $team]));
        $delete->assertOk();

        $this->assertDatabaseMissing('admin_teams', ['at_id' => $team->at_id]);
    }
}
