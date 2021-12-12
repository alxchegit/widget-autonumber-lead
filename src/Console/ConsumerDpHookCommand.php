<?php

namespace App\Console;

use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\BirthdayCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateTimeCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;

use App\Helpers\WLogger;
use App\Model\Process;
use App\Controller\AmoController;
use App\Model\Counters;
use Illuminate\Database\Capsule\Manager as Capsule;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command.
 */
final class ConsumerDpHookCommand extends Command
{
    /**
     * @var
     */
    private $config;

    /**
     * @var Capsule
     */
    private $capsule;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct();

        WLogger::log_it('consumer is booting', __LINE__);

        $this->config = $config;
        $this->capsule = new Capsule;

        // Подключаем соединение
        if (isset($this->config['db'])) {
            $this->capsule->addConnection($this->config['db']);
            $this->capsule->setAsGlobal();
            $this->capsule->bootEloquent();
        }
    }

    /**
     * Configure.
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('consumer');
        $this->setDescription('Queue handler.');
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     *
     * @return int The error code, 0 on success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        WLogger::log_it('executing consumer', __LINE__);
        $this->output = $output;

        $output->writeln(sprintf('<info>Start</info>'));

        $this->consumer();

        $output->writeln(sprintf('<info>Done</info>'));

        return 0;
    }

    private function consumer()
    {
        $connection = new AMQPStreamConnection('localhost', 5672, 'admin', 'r_q3DudzBg');
        $channel = $connection->channel();

        $channel->queue_declare('autonumber_lead', false, true, false, false);

        $this->output->write('[*] Waiting for messages. To exit press CTRL+C', true);

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume('autonumber_lead', '', false, false, false, false, [$this, 'process']);

        while ($channel->is_consuming()) {
            $this->output->write('Отслеживаю входящие сообщения!', true);
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    /**
     * @param AMQPMessage $msg
     * @return bool
     */
    public function process(AMQPMessage $msg)
    {
        WLogger::log_it('Started handling request, data: ', __LINE__);
        WLogger::log_it($msg->body, __LINE__);

        $post_data = json_decode($msg->body, true);
        $this->output->write(sprintf('[x] new post request processing... %s', $msg->body), true);

        // Получаем данные по событию
        if ($post_data['event']['data']['element_type'] != 2) {
            $this->output->write('Неподходящее событие!', true);
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

            return false;
        }

        // ID аккаунта
        $account_id = $post_data['account_id'];

        // ID сделки
        $lead_id = $post_data['event']['data']['id']; 

        // ID процесса
        $process_id = $post_data['action']['settings']['widget']['settings']['params'];

        // ID pipeline
        $pipeline_id = $post_data['event']['data']['pipeline_id'];

        // ID status
        $status_id = $post_data['event']['data']['status_id']; 

        // Получаем процесс
        $process = Process::find($process_id);

        if (!$process) {
            WLogger::log_it('Процесс с id ' . $process_id . ' аккаунта ' . $account_id . ' не зарегистрирован в системе!', __LINE__);
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

            return false;
        }

        WLogger::log_it('Процесс с id ' . $process_id . ' аккаунта ' . $account_id . ' успешно получен! Процесс:', __LINE__);
        WLogger::log_it($process->toArray(), __LINE__);

        // Настройки полей
        $conditions = json_decode($process->conditions, true);
        
        WLogger::log_it("Для процесса ($process_id) на аккаунте($account_id) и воронки($pipeline_id), текущее значение счетчика - $process->counter", __LINE__);
         
         // увеличим счетчик на 1
         $process->counter = $process->counter + 1;
         if($process->save()){
            $counter_value = $process->counter;
            WLogger::log_it("Счетчик увеличен на 1 и равен теперь - " . $counter_value, __LINE__);
         }

        // AMO клиент
        $apiClient = (new AmoController($this->config))->auth($account_id);

        if (!$apiClient) {
            WLogger::log_it('Не удалось авторизоваться в amoCRM!', __LINE__);
            return false;
        }

        try {
            // Получим сделку
            $lead = $apiClient->leads()->getOne($lead_id);

            $customFieldsValuesCollection = new CustomFieldsValuesCollection();

            foreach($conditions as $cond){
                // id поля для заполнения
                $cf_id = $cond['anum_field'];
                // маска поля
                $anum_tpl = $cond['anum_tpl'];
                // тип поля
                $cf_type = $cond['anum_field_type'];
    
                $start = 0;
                if(preg_match('/\{\#.*?\#\}/', $anum_tpl, $match)){
                    $start = (int)trim(str_replace(['{#','#}'],'',$match[0]));
                    $anum_tpl = preg_replace('/\{\#.*?\#\}/', '', $anum_tpl);
                }
    
                WLogger::log_it('Строка в начале - "'. $anum_tpl . '"', __LINE__);

                // подготовим маски
                $nine_mask = str_pad(''.($start+$counter_value), 9, '0', STR_PAD_LEFT);
                $six_mask = str_pad(''.($start+$counter_value), 6, '0', STR_PAD_LEFT);
                $no_pad = $start + $counter_value;

                // перепишем строку
                $anum_tpl = str_replace('{{000000001}}', $nine_mask, $anum_tpl);
                $anum_tpl = str_replace('{{000001}}', $six_mask, $anum_tpl);
                $anum_tpl = str_replace('{{1}}', $no_pad, $anum_tpl);
                
                WLogger::log_it('Строка в конце - "'. $anum_tpl . '"', __LINE__);
                
                if($cf_type === 'text'){
                    $fieldModel = new TextCustomFieldValuesModel();
                    $fieldModel->setFieldId($cf_id);
                    $fieldModel = $fieldModel->setValues(
                        (new TextCustomFieldValueCollection())
                        ->add(
                            (new TextCustomFieldValueModel())->setValue($anum_tpl)));
                    $customFieldsValuesCollection->add($fieldModel);
                    
                } elseif($cf_type === 'numeric') {
                    $fieldModel = new NumericCustomFieldValuesModel();
                    $fieldModel->setFieldId($cf_id);
                    $fieldModel = $fieldModel->setValues(
                        (new NumericCustomFieldValueCollection())
                        ->add(
                            (new NumericCustomFieldValueModel())->setValue((int)preg_replace('/\D/', '', $anum_tpl))));
                    $customFieldsValuesCollection->add($fieldModel);
                }
                            
            }
            // запишем поля
            $lead->setCustomFieldsValues($customFieldsValuesCollection);
            $apiClient->leads()->updateOne($lead);

            WLogger::log_it('Поля успешно переписаны!', __LINE__);
        } catch (AmoCRMApiException $e) {
            WLogger::log_it('Exeption!', __LINE__);
            WLogger::log_it($e->getMessage(), __LINE__);
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

            return false;
        } catch (\Exception $e){
            WLogger::log_it('Exeption!', __LINE__);
            WLogger::log_it($e->getMessage(), __LINE__);
        }

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }

}