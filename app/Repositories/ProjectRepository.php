<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Auth;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Class ProjectRepository.
 */
class ProjectRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name',
        'team',
        'description',
        'client_id',
    ];

    /**
     * Return searchable fields.
     *
     * @return array
     */
    public function getFieldsSearchable()
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model.
     **/
    public function model()
    {
        return Project::class;
    }

    /**
     * @param array $input
     *
     * @return Project
     */
    public function store($input)
    {
        $input['created_by'] = getLoggedInUserId();
        $input['description'] = is_null($input['description']) ? '' : $input['description'];

        $project = Project::create($input);
        $project->users()->sync($input['user_ids']);

        return $project->fresh();
    }

    /**
     * @param array $input
     * @param int   $id
     *
     * @return Project
     */
    public function update($input, $id)
    {
        $input['description'] = is_null($input['description']) ? '' : $input['description'];
        $project = Project::findOrFail($id);
        $project->update($input);
        $project->users()->sync($input['user_ids']);

        return $project->fresh();
    }

    /***
     * @return array
     */
    public function getLoginUserAssignProjectsArr()
    {
        $loggedInUser = getLoggedInUser();

        if ($loggedInUser->can('manage_projects')) {
            return $this->getProjectsList()->toArray();
        }

        return Auth::user()->projects()->orderBy('name')->get()->pluck('name', 'id')->toArray();
    }

    /**
     * get clients.
     *
     * @param int|null $clientId
     *
     * @return Collection
     */
    public function getProjectsList($clientId = null)
    {
        /** @var Builder|Project $query */
        $query = Project::orderBy('name');
        if (!is_null($clientId)) {
            $query = $query->whereClientId($clientId);
        }

        return $query->pluck('name', 'id');
    }

    /**
     * @return Project[]
     */
    public function getMyProjects()
    {
        $query = Project::whereHas('users', function (Builder $query) {
            $query->where('user_id', getLoggedInUserId());
        });

        /** @var Project[] $projects */
        $projects = $query->orderBy('name')->get();

        return $projects;
    }

    /**
     * @param int $id
     *
     * @throws Exception
     *
     * @return bool|mixed|void|null
     */
    public function delete($id)
    {
        /** @var Project $project */
        $project = $this->find($id);

        $taskIds = Task::whereProjectId($project->id)->pluck('id')->toArray();
        TimeEntry::whereIn('task_id', $taskIds)->update(['deleted_by' => getLoggedInUserId()]);
        TimeEntry::whereIn('task_id', $taskIds)->delete();

        $project->tasks()->update(['deleted_by' => getLoggedInUserId()]);
        $project->tasks()->delete();

        $project->update(['deleted_by' => getLoggedInUserId()]);
        $project->delete();
    }
}
