<?php

namespace App\Controller;

use App\Helpers\ResponseHelper;
use App\Helpers\WLogger;
use App\Model\Process;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;

class WidgetController extends BaseController
{
    /**
     * Конструктор.
     * @param array $config Массив параметров.
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * @param $request
     * @param $response
     * @return ResponseInterface
     */
    public function process($request, $response)
    {
        $data = $request->getParsedBody();

        try {
            return ResponseHelper::success($response, [
                'process' => Process::query()->where('id', $data['process_id'])->first()
            ]);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::error($response, ['msg' => 'Процесс не найден!', 'e' => $e->getMessage()]);
        }
    }

    /**
     * @param $request
     * @param $response
     * @return mixed
     */
    public function saveProcess($request, $response)
    {
        $postData = $request->getParsedBody();

        // Берем запись из БД, если записи нет - создаем
        $process = Process::find($postData['process_id']);

        if (!$process) {
            $process = new Process;
            $process->account_id = $postData['account_id'];
            $process->conditions = json_encode($postData['conditions']);

            $process->save();
        } else{
            Process::query()->where('id', $postData['process_id'])->update([
                'conditions' => json_encode($postData['conditions'])
            ]);
        }

        return ResponseHelper::success($response, ['msg' => 'Процесс успешно обновлен!', 'process_id' => $process->id]);
    }

    /**
     * @param $request
     * @param $response
     * @return ResponseInterface
     */
    public function deleteProcess($request, $response)
    {
        $data = $request->getParsedBody();

        // Получаем процесс
        try {
            $process = Process::query()->findOrFail($data['process_id']);

            if ($process->delete()) {
                return ResponseHelper::success($response, ['msg' => 'Процесс успешно удален!', 'id' => $process->id]);
            }
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::success($response, ['msg' => 'Процесс успешно удален!']);
        } catch (\Exception $e) {
            WLogger::log_it('Возникла ошибка при удалении процесса! Exception: ' . $e->getMessage(), __LINE__);
        }

        return ResponseHelper::error($response, ['msg' => 'Возникла ошибка при удалении процесса!']);
    }

}