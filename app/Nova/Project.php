<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\HasMany;

class Project extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = 'App\Project';

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = ['id', 'title'];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(),
            BelongsTo::make('User')->rules('required'),
            // HasOne::make('Simulation'),
            Text::make('Title')
                ->rules('required')
                ->sortable(),
            Select::make('Currency')->options([
                'usd' => '＄',
                'jpy' => '￥',
                'eur' => '€'
            ]),
            Select::make('Business Model')
                ->options([
                    'subscription' => 'Subscription Based'
                ])
                ->hideFromIndex(),
            // Text::make('URL'),
            Date::make('Start Date')
                ->rules('required')
                ->sortable(),
            // Number::make('Duration')
            //     ->rules('required')
            //     ->sortable(),
            // Number::make('Financial Month')->rules('required'),
            // Boolean::make('With Model'),
            // Boolean::make('With Launch'),

            DateTime::make('Created At')->sortable(),
            DateTime::make('Updated At')->hideFromIndex(),
            HasMany::make('Records')
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
