<?php

namespace App\Http\Controllers\Api;

use App\Project;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\CreateProject;
use App\Http\Requests\Api\UpdateProject;
use App\Http\Requests\Api\DeleteProject;
use App\Http\Requests\Api\CreateSimulation;
use App\Http\Requests\Api\UpdateSimulation;
use App\Http\Transformers\ProjectTransformer;
use App\Notifications\SubscriptionStarted as SubscriptionStartedNotification;
use App\Notifications\SubscriptionChanged as SubscriptionChangedNotification;
use App\Notifications\TrialStarted as TrialStartedNotification;

use Illuminate\Support\Facades\Lang;

class ProjectController extends ApiController
{
    /**
     * ProjectController constructor.
     *
     * @param ProjectTransformer $transformer
     */
    public function __construct(ProjectTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Get all the projects.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $projects = user()
                ->projects()
                ->get();
        } catch (\Exception $e) {
            return $this->respondError("No projects", 404);
        }
        return $this->respondWithTransformer($projects);
    }

    /**
     * Get all foreign projects.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function team()
    {
        try {
            $projects = user()
                ->team_projects()
                ->get();
        } catch (\Exception $e) {
            return $this->respondError("No projects", 400);
        }
        return $this->respondWithTransformer($projects);
    }

    /**
     * Get the project team members by project id.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function members($id)
    {
        $project = Project::where('id', $id)->firstOrFail();
        return $this->respond(['members' => $project->team_members]);
    }

    /**
     * Create a new project and return the project if successful.
     *
     * @param CreateProject $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateProject $request)
    {
        $user = user();
        $data = $request->get('project');
        $data['start_date'] = as_date($data['start_date']);

        $plan_id = array_get($data, 'plan.id');
        $plan_name = array_get($data, 'plan.name');
        $token = array_get($data, 'plan.token');
        $coupon = array_get($data, 'coupon');
        $model = array_get($data, 'model');

        // trying to create trial project while already have projects
        if (!$plan_id && !$user->hasTrialCapacity()) {
            return $this->respondError(Lang::getFromJson("Payment required"), 402);
        }
        if (!$user->stripe_id) {
            $user->createAsStripeCustomer();
        }
        $data = array_only($data, [
            'title',
            'currency',
            'start_date',
            'business_model',
            'with_cost_manager'
        ]);
        $data['business_model'] =
            $data['business_model'] == 'kickstart' ? 'kickstart' : 'subscription';
        $data['duration'] = 24;
        $data['stripe_id'] = $user->stripe_id;
        $data['card_brand'] = $user->card_brand;
        $data['card_last_four'] = $user->card_last_four;

        $project = $user->projects()->create($data);

        if ($plan_id) {
            try {
                $project->addSubscription($plan_id, $coupon, $token);
            } catch (\Exception $e) {
                $project->forceDelete();
                return $this->respondError($e->getMessage(), 402);
            }
            $user->notify(new SubscriptionStartedNotification($plan_name));
        } else {
            $user->trial_ends_at = now()->addDays(30);
            $user->save();
            $user->notify(new TrialStartedNotification());
        }

        // $model['initial_cost'] = array_get($model, 'initial_cost', 0);
        // if ($project->with_launch && $model && $model['launch_period']) {
        //     $model = $project->simulation()->create($model);
        //     $record_data = [
        //         'type' => 'launch',
        //         'planned' => true,
        //         'category' => 'initial',
        //         'name' => '',
        //         'period' => [
        //             'start_date' => $project->start_date->subMonths(
        //                 $project->simulation->launch_period
        //             ),
        //             'duration' => $model->launch_period
        //         ],
        //         'cost' => [
        //             'price' => $model->initial_cost / ($model->launch_period ?: 1)
        //         ]
        //     ];
        //     $record = $project->records()->create(normalize_cost($record_data, $project));
        //     $record->autoFill(0, $model->launch_period);
        // }

        return $this->respondWithTransformer($project);
    }

    /**
     * Get the project given by its id.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $project = Project::with(['user', 'simulation'])
            ->where('id', $id)
            ->firstOrFail();
        if (
            $project->user_id != auth()->id() &&
            user()->acl < 9 &&
            !user()->has_access($project->id)
        ) {
            return $this->respondForbidden("Access Denied");
        }

        $project->subs = $project->subscription('default');
        return $this->respondWithTransformer($project);
    }

    /**
     * Update the project given by its id and return the project if successful.
     *
     * @param UpdateProject $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProject $request, Project $project)
    {
        $user = user();
        $data = $request->get('project');
        $plan_id = array_get($data, 'plan.id');
        $plan_name = array_get($data, 'plan.name');
        $token = array_get($data, 'plan.token');
        $coupon = array_get($data, 'coupon');

        $data = array_only($data, [
            'title',
            'currency',
            'start_date',
            'business_model',
            'with_launch',
            'with_cost_manager'
        ]);
        $data['business_model'] =
            $data['business_model'] == 'kickstart' ? 'kickstart' : 'subscription';
        $project->update($data);

        // $data_model = $request->input('project.model');
        // if ($project->with_launch && $data_model && $data_model['launch_period']) {
        //     $model = $project->simulation;
        //     if (!$model) {
        //         $project->simulation()->create($data_model);
        //     } else {
        //         $model->launch_period = $data_model['launch_period'];
        //         $model->initial_cost = $data_model['initial_cost'];
        //         $model->save();
        //     }
        // }

        $subscription = $project->subscription('default');

        if (!$subscription && !$plan_id) {
            return $this->respondWithTransformer($project);
        }

        if (!$subscription) {
            if (!$user->stripe_id) {
                $user->createAsStripeCustomer();
            }
            $project->stripe_id = $user->stripe_id;
            $project->card_brand = $user->card_brand;
            $project->card_last_four = $user->card_last_four;
            $project->save();
            $project->addSubscription($plan_id, $coupon, $token);
        } elseif ($subscription->stripe_plan != $plan_id) {
            if (!empty($coupon)) {
                $subscription->withCoupon($coupon);
            }
            $subscription->noProrate()->swap($plan_id);
            $user->notify(new SubscriptionChangedNotification($plan_name));
        }

        return $this->respondWithTransformer($project);
    }

    /**
     * Delete the project given by its id.
     *
     * @param DeleteProject $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteProject $request, Project $project)
    {
        try {
            $project->cancelSubscription(true);
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
        }
        $project->delete();

        return $this->respondSuccess();
    }

    /**
     * Create project model simulation given by project id and return the model if successful.
     *
     * @param CreateSimulation $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function modelCreate(CreateSimulation $request, Project $project)
    {
        //TODO: check owner
        if (!$request->has('model')) {
            return $this->respondError("no model data present", 400);
        }
        $model = $project->simulation()->create($request->get('model'));

        return $this->respond(compact('model'));
    }

    /**
     * Update project model simulation given by project id and return the model if successful.
     *
     * @param UpdateSimulation $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function modelUpdate(UpdateSimulation $request, Project $project)
    {
        //TODO: check owner
        if (!$request->has('model')) {
            return $this->respondError("no model data present", 400);
        }
        $model = $project->simulation()->update($request->get('model'));

        return $this->respond(['model' => $model]);
    }

    public function getSubscription(Project $project)
    {
        $user = user();
        if (!$user->stripe_id) {
            return $this->respond(['cost' => 0, 'error' => 'invalid customer']);
        }
        if ($user->id != $project->user_id) {
            return $this->respondForbidden("Access Denied");
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $subscription = \Stripe\Subscription::retrieve(
            $project->subscription('default')->stripe_id
        );

        return $this->respond(compact('subscription'));
    }

    public function cancelSubscription(Project $project)
    {
        $user = user();
        if (!$user->stripe_id) {
            return $this->respond(['cost' => 0, 'error' => 'invalid customer']);
        }
        if ($user->id != $project->user_id) {
            return $this->respondForbidden("Access Denied");
        }

        $project->cancelSubscription();
        // event(new SubscriptionCancelled($request->user()->fresh()));
        return $this->respondSuccess();
    }

    public function resumeSubscription(Project $project)
    {
        $user = user();
        if (!$user->stripe_id) {
            return $this->respond(['cost' => 0, 'error' => 'invalid customer']);
        }
        if ($user->id != $project->user_id) {
            return $this->respondForbidden("Access Denied");
        }

        $project->resumeSubscription();
        // event(new SubscriptionCancelled($request->user()->fresh()));
        return $this->respondSuccess();
    }

    public function getBeacon(Project $project)
    {
        $beacon = $project->beacon;
        return $this->respond(compact('beacon'));
    }
}
