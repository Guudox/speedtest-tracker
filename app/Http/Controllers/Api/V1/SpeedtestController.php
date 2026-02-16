<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RunSpeedtest as RunSpeedtestAction;
use App\Enums\ResultService;
use App\Http\Resources\V1\ResultResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class SpeedtestController extends ApiController
{
    /**
     * POST /api/v1/speedtests/run
     * Run a new speedtest.
     */
    public function __invoke(Request $request)
    {
        if ($request->user()->tokenCant('speedtests:run')) {
            return $this->sendResponse(
                data: null,
                message: 'You do not have permission to run speedtests.',
                code: Response::HTTP_FORBIDDEN,
            );
        }

        if ($request->filled('service')) {
            $request->merge([
                'service' => strtolower((string) $request->input('service')),
            ]);
        }

        $service = ResultService::tryFrom((string) $request->input('service', config('speedtest.service', ResultService::Ookla->value)))
            ?? ResultService::Ookla;

        $validator = Validator::make($request->all(), [
            'service' => ['sometimes', 'string', Rule::in([ResultService::Ookla->value, ResultService::Iperf3->value])],
            'server_id' => [
                'nullable',
                'integer',
            ],
            'host' => [
                'nullable',
                'string',
                'max:255',
            ],
            'port' => [
                'nullable',
                'integer',
                'between:1,65535',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendResponse(
                data: $validator->errors(),
                message: 'Validation failed.',
                code: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $result = RunSpeedtestAction::run(
            scheduled: true,
            service: $service,
            serverId: $request->input('server_id'),
            host: $request->input('host'),
            port: $request->integer('port') ?: null,
            dispatchedBy: $request->user()->id,
        );

        return $this->sendResponse(
            data: new ResultResource($result),
            message: 'Speedtest added to the queue.',
            code: Response::HTTP_CREATED,
        );
    }
}
