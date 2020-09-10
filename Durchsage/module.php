<?php

declare(strict_types=1);

include_once __DIR__ . '/libs/WebHookModule.php';

class Durchsage extends WebHookModule
{
    const DS_SONOS = 0;
    const DS_MEDIA = 1;
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID, 'durchsage-sonos');
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('PollyID', 0);
        $this->RegisterPropertyInteger('OutputType', 0);
        $this->RegisterPropertyInteger('OutputInstance', 0);
        $this->RegisterPropertyString('SonosIP', Sys_GetNetworkInfo()[0]['IP']);
        $this->RegisterPropertyString('SonosVolume', '0');
        $this->RegisterPropertyInteger('MediaPlayerVolume', 50);

        //Variables
        $this->RegisterVariableString('TTSText', 'Text', '', 0);
        $this->EnableAction('TTSText');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->setIntanceStatus();
    }

    public function RequestAction($Ident, $Value)
    {
        $this->SetValue($Ident, $Value);

        switch ($Ident) {
            case 'TTSText':
                $this->Play($Value);
            break;

            default:
                $this->LogMessage(KL_WARNING, $this->Translate('Unknown ident in RequestAction'));
            }
    }

    public function Play(string $Text)
    {
        $this->setIntanceStatus();
        if ($this->GetStatus() != 102) {
            return;
        }
        if (IPS_GetProperty($this->ReadPropertyInteger('PollyID'), 'OutputFormat') != 'mp3') {
            $this->LogMessage(KL_ERROR, $this->Translate('Only mp3 is supported'));
            return;
        }

        //Providing audio data to the WebHook
        $this->SetBuffer('AudioData', base64_decode(TTSAWSPOLLY_GenerateData($this->ReadPropertyInteger('PollyID'), $Text)));
        switch ($this->ReadPropertyInteger('OutputType')) {
            case self::DS_SONOS:
                //Setting filename to allow the Sonos module to fetch the mime type
                SNS_PlayFiles($this->ReadPropertyInteger('OutputInstance'), json_encode([sprintf('http://%s:3777/hook/durchsage-sonos/Durchsage.mp3', $this->ReadPropertyString('SonosIP'))]), $this->ReadPropertyString('SonosVolume'));
            break;

            case self::DS_MEDIA:
                //No volume reset
                WAC_SetVolume($this->ReadPropertyInteger('OutputInstance'), $this->ReadPropertyInteger('MediaPlayerVolume'));
                //Fading takes 500ms
                IPS_Sleep(500);
                WAC_PlayFile($this->ReadPropertyInteger('OutputInstance'), 'http://127.0.0.1:3777/hook/durchsage-sonos/');
            break;
        }
    }
    public function UpdateOutput($OutputType)
    {
        //Disabling elements temporarily in order to prevent errors
        $this->UpdateFormField('OutputInstance', 'value', 0);
        $this->UpdateFormField('OutputInstance', 'enabled', false);
        switch ($OutputType) {
            case self::DS_SONOS:
                $this->UpdateFormField('MediaPlayerVolume', 'enabled', false);
            break;

            case self::DS_MEDIA:
                $this->UpdateFormField('SonosVolume', 'enabled', false);
                $this->UpdateFormField('SonosIP', 'enabled', false);
            break;

            default:
                $this->LogMessage(KL_ERROR, $this->Translate('Unknown output type'));

        }
    }
    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $outputOptions[] = [
            'value'   => 0,
            'caption' => 'Sonos'
        ];
        if (strpos(IPS_GetKernelPlatform(), 'Windows') !== false) {
            $outputOptions[] = [
                'value'   => 1,
                'caption' => 'Media Player'
            ];
        }
        $form['elements'][1] = [
            'type'     => 'Select',
            'name'     => 'OutputType',
            'caption'  => $this->Translate('Output Device'),
            'options'  => $outputOptions,
            'onChange' => 'DS_UpdateOutput($id, $OutputType);'

        ];
        switch ($this->ReadPropertyInteger('OutputType')) {
            case self::DS_SONOS:
                $ipOptions = [];
                $networkInfo = Sys_GetNetworkInfo();
                for ($i = 0; $i < count($networkInfo); $i++) {
                    $ipOptions[] = [
                        'caption' => $networkInfo[$i]['IP'],
                        'value'   => $networkInfo[$i]['IP']
                    ];
                }
                $form['elements'][2] = [
                    'type'    => 'Select',
                    'name'    => 'SonosIP',
                    'caption' => $this->Translate('Sonos IP'),
                    'options' => $ipOptions
                ];

                $form['elements'][3] = [
                    'type'    => 'Select',
                    'name'    => 'OutputInstance',
                    'caption' => 'Sonos Player',
                    'options' => $this->getInstanceOptions('{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}')
                ];
                $form['elements'][4] = [
                    'type'     => 'ValidationTextBox',
                    'name'     => 'SonosVolume',
                    'caption'  => 'Volume',
                    'validate' => '^[-+]?[0-9][0-9]?$|^100$',
                    'value'    => '0'
                ];
            break;

            case self::DS_MEDIA:

                $form['elements'][2] = [
                    'type'    => 'Select',
                    'name'    => 'OutputInstance',
                    'caption' => 'Media Player',
                    'options' => $this->getInstanceOptions('{2999EBBB-5D36-407E-A52B-E9142A45F19C}')
                ];
                $form['elements'][3] = [
                    'type'    => 'NumberSpinner',
                    'name'    => 'MediaPlayerVolume',
                    'caption' => 'Volume',
                    'minimum' => 0,
                    'maximum' => 100
                ];

            break;
        }

        return json_encode($form);
    }

    protected function ProcessHookData()
    {
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . strlen($this->GetBuffer('AudioData')));
        echo $this->GetBuffer('AudioData');
    }

    private function setIntanceStatus()
    {
        $polly = $this->ReadPropertyInteger('PollyID');
        $newStatus = 102;
        if ($polly != 0) {
            if (IPS_InstanceExists($polly)) {
                if (IPS_GetInstance($polly)['ModuleInfo']['ModuleID'] != '{6EFA02E1-360F-4120-B3DE-31EFCDAF0BAF}') {
                    $newStatus = 201;
                }
            } else {
                $newStatus = 200;
            }
        } else {
            $newStatus = 104;
        }
        $output = $this->ReadPropertyInteger('OutputInstance');
        if ($output != 0) {
            if (!IPS_InstanceExists(($output))) {
                $newStatus = 202;
            }
        }
        $this->SetStatus($newStatus);
    }

    private function getInstanceOptions($guid)
    {
        $instances = IPS_GetInstanceListByModuleID($guid);
        $options[] = [
            'value'   => 0,
            'caption' => $this->Translate('None')
        ];
        foreach ($instances as $instance) {
            $options[] = [
                'value'   => $instance,
                'caption' => IPS_GetName($instance)
            ];
        }
        return $options;
    }
}