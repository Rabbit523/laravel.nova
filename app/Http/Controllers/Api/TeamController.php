<?php

namespace App\Http\Controllers\Api;

use App\Project;
use App\Team;
use App\User;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\CreateTeam;
use App\Http\Requests\Api\DeleteTeam;
use App\Http\Requests\Api\ManageTeamProject;
use App\Http\Requests\Api\InviteTeamMember;
use App\Http\Transformers\TeamTransformer;

use App\Notifications\TeamJoined as TeamJoinedNotification;
use App\Notifications\TeamLeft as TeamLeftNotification;
use App\Notifications\TeamMemberJoined as TeamMemberJoinedNotification;
use App\Notifications\TeamMemberRemoved as TeamMemberRemovedNotification;

class TeamController extends ApiController
{
    /**
     * TeamController constructor.
     *
     * @param TeamTransformer $transformer
     */
    public function __construct(TeamTransformer $transformer)
    {
        $this->transformer = $transformer;
        // $this->middleware('subscribed')->except(['index', 'show', 'joined', 'removeMember']);
    }

    /**
     * Get all the teams.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $teams = user()
                ->teams()
                ->withCount(['projects', 'members'])
                ->get();
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
            return $this->respond(['teams' => []]);
        }
        return $this->respondWithTransformer($teams);
    }

    /**
     * Get all the teams.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function joined()
    {
        try {
            $teams = user()
                ->joined_teams()
                ->withCount(['projects', 'members'])
                ->get();
        } catch (\Exception $e) {
            return $this->respondError("No teams", 404);
        }
        return $this->respondWithTransformer($teams);
    }

    /**
     * Create a new team and return the team if successful.
     *
     * @param CreateTeam $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateTeam $request)
    {
        $team = user()
            ->teams()
            ->create([
                'name' => $request->input('team.name')
            ]);

        return $this->respondWithTransformer($team);
    }

    /**
     * Get the team given by its id.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $team = Team::with(['projects', 'members'])
            ->where('id', $id)
            ->firstOrFail();
        if (
            $team->user_id != auth()->id() &&
            user()->acl < 9 &&
            !$team->members->contains(auth()->id())
        ) {
            return $this->respondForbidden();
        }

        return $this->respondWithTransformer($team);
    }

    /**
     * Update the team given by its id and return the team if successful.
     *
     * @param Team $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Team $team)
    {
    }

    /**
     * Delete the team given by its id.
     *
     * @param DeleteTeam $request
     * @param Team $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteTeam $request, Team $team)
    {
        $team->delete();

        return $this->respondSuccess();
    }

    /**
     * Add project to the team
     *
     * @param ManageTeamProject $request
     * @param Team $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function addProject(ManageTeamProject $request, Team $team)
    {
        $project = Project::findOrFail($request->input('project.id'));
        if ($project->user_id != auth()->id() && user()->acl < 9) {
            return $this->respondForbidden("wrong project");
        }
        $team->projects()->syncWithoutDetaching([$project->id]);

        return $this->respondSuccess();
    }

    /**
     * Remove project from the team
     *
     * @param Team $team
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeProject(Team $team, Project $project)
    {
        if ($team->user_id != auth()->id() && user()->acl < 9) {
            return $this->respondForbidden("wrong team");
        }
        if ($project->user_id != auth()->id() && user()->acl < 9) {
            return $this->respondForbidden("wrong project");
        }
        $team->projects()->detach($project->id);

        return $this->respondSuccess();
    }

    /**
     * Add project to the team
     *
     * @param InviteTeamMember $request
     * @param Team $team
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMember(InviteTeamMember $request, Team $team)
    {
        $user = User::email($request->input('member.email'))->first();

        if (!$user) {
            // TODO: send invite? create placeholder?
            return $this->respondError("User not found", 400);
        }

        $result = $team->members()->syncWithoutDetaching([$user->id]);

        if (count($result['attached'])) {
            $user->notify(new TeamJoinedNotification($team, user()));
            $team->notify(new TeamMemberJoinedNotification(user(), $user));
        }

        return $this->respondSuccess();
    }

    /**
     * Remove member from the team
     *
     * @param Team $team
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(Team $team, User $user)
    {
        if ($team->user_id != auth()->id() && user()->acl < 9 && auth()->id() != $user->id) {
            return $this->respondForbidden("wrong team");
        }

        $result = $team->members()->detach($user->id);

        if ($result) {
            if ($user->id != auth()->id()) {
                $user->notify(new TeamMemberRemovedNotification($team, user()));
            }
            $team->notify(new TeamLeftNotification($team, $user));
        }

        return $this->respondSuccess();
    }
}
