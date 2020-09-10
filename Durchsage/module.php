<?php

declare(strict_types=1);

include_once __DIR__ . '/libs/WebHookModule.php';

class Durchsage extends WebHookModule
{
    const DS_SONOS = 0;
    const DS_MEDIA = 1;
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID, 'durchsage/' . $InstanceID);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('PollyID', 0);
        $this->RegisterPropertyInteger('OutputType', self::DS_SONOS);
        $this->RegisterPropertyInteger('OutputInstance', 0);
        $this->RegisterPropertyString('SymconIP', Sys_GetNetworkInfo()[0]['IP']);
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
        $this->setInstanceStatus();
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
        $this->setInstanceStatus();
        $status = $this->GetStatus();
        if ($status != 102) {
            switch ($status) {
                case 204:
                    echo $this->Translate('The Output Format of AWS Polly needs to be mp3');
                    break;

                case 205:
                    echo $this->Translate('The selected Sample Rate of AWS Polly is not supportet. Choose one of the following: 16000 , 22050, 24000, 32000, 44100, 48000');
                    break;

                default:
                    return;
            }
        }

        //Providing audio data to the WebHook
        $this->SetBuffer('AudioData', base64_decode(TTSAWSPOLLY_GenerateData($this->ReadPropertyInteger('PollyID'), $Text)));
        switch ($this->ReadPropertyInteger('OutputType')) {
            case self::DS_SONOS:
                //Setting filename to allow the Sonos module to fetch the mime type
                SNS_PlayFiles($this->ReadPropertyInteger('OutputInstance'), json_encode([sprintf('http://%s:3777/hook/durchsage/%s/Durchsage.mp3', $this->ReadPropertyString('SymconIP'), $this->InstanceID)]), $this->ReadPropertyString('SonosVolume'));
                break;

            case self::DS_MEDIA:
                //No volume reset
                WAC_SetVolume($this->ReadPropertyInteger('OutputInstance'), $this->ReadPropertyInteger('MediaPlayerVolume'));
                //Fading takes 500ms
                IPS_Sleep(500);
                WAC_PlayFile($this->ReadPropertyInteger('OutputInstance'), 'http://127.0.0.1:3777/hook/durchsage/' . $this->InstanceID);
                break;
        }
    }

    public function UpdateOutput($OutputType)
    {
        switch ($OutputType) {
            case self::DS_SONOS:
                //Hide Media Player elements
                $this->UpdateFormField('MediaPlayerVolume', 'visible', false);

                //Update output select
                $this->UpdateFormField('OutputInstance', 'caption', $this->Translate('Sonos Player'));
                $this->UpdateFormField('OutputInstance', 'options', json_encode($this->getInstanceOptions('{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}')));
                $this->UpdateFormField('OutputInstance', 'value', 0);

                //Show Sonos
                $this->UpdateFormField('SonosVolume', 'visible', true);
                $this->UpdateFormField('SymconIP', 'visible', true);
                break;

            case self::DS_MEDIA:
                //Hide Sonos elements
                $this->UpdateFormField('SonosVolume', 'visible', false);
                $this->UpdateFormField('SymconIP', 'visible', false);

                //Update output select
                $this->UpdateFormField('OutputInstance', 'caption', $this->Translate('Media Player'));
                $this->UpdateFormField('OutputInstance', 'options', json_encode($this->getInstanceOptions('{2999EBBB-5D36-407E-A52B-E9142A45F19C}')));
                $this->UpdateFormField('OutputInstance', 'value', 0);

                //Show Media Player
                $this->UpdateFormField('MediaPlayerVolume', 'visible', true);
                break;

            default:
                $this->LogMessage(KL_ERROR, $this->Translate('Unknown output type'));

        }
    }
    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $form['elements'][0] = [
            'type'    => 'Select',
            'name'    => 'PollyID',
            'caption' => 'Text-to-Speech Instance (Polly)',
            'options' => $this->getInstanceOptions('{6EFA02E1-360F-4120-B3DE-31EFCDAF0BAF}')
        ];

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
            'name'    => 'SymconIP',
            'caption' => $this->Translate('Symcon IP'),
            'options' => $ipOptions,
            'visible' => $this->ReadPropertyInteger('OutputType') === self::DS_SONOS
        ];

        $form['elements'][3] = [
            'type'    => 'Select',
            'name'    => 'OutputInstance',
            'caption' => $this->ReadPropertyInteger('OutputType') === self::DS_MEDIA ? 'Media Player' : 'Sonos Player',
            'options' => $this->getInstanceOptions($this->ReadPropertyInteger('OutputType') === self::DS_MEDIA ? '{2999EBBB-5D36-407E-A52B-E9142A45F19C}' : '{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}')
        ];
        $form['elements'][4] = [
            'type'     => 'ValidationTextBox',
            'name'     => 'SonosVolume',
            'caption'  => 'Volume',
            'validate' => '^[-+]?[0-9][0-9]?$|^100$',
            'value'    => '0',
            'visible'  => $this->ReadPropertyInteger('OutputType') === self::DS_SONOS
        ];

        $form['elements'][5] = [
            'type'    => 'NumberSpinner',
            'name'    => 'MediaPlayerVolume',
            'caption' => 'Volume',
            'minimum' => 0,
            'maximum' => 100,
            'visible' => $this->ReadPropertyInteger('OutputType') === self::DS_MEDIA
        ];

        return json_encode($form);
    }

    protected function ProcessHookData()
    {
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . strlen($this->GetBuffer('AudioData')));
        echo $this->GetBuffer('AudioData');
    }

    private function setInstanceStatus()
    {
        $getInstanceStatus = function ()
        {
            $polly = $this->ReadPropertyInteger('PollyID');
            if ($polly === 0) {
                return 104;
            }
            if (!IPS_InstanceExists($polly)) {
                return 200;
            }
            if (IPS_GetInstance($polly)['ModuleInfo']['ModuleID'] != '{6EFA02E1-360F-4120-B3DE-31EFCDAF0BAF}') {
                return 201;
            }
            if (IPS_GetProperty($this->ReadPropertyInteger('PollyID'), 'OutputFormat') != 'mp3') {
                return 204;
            }
            if ($this->ReadPropertyInteger('OutputType') === self::DS_SONOS) {
                //Sonos only supports following sample rates: https://support.sonos.com/s/article/79?language=de
                $sampleRateAllowList = ['', '16000', '22050', '24000', '32000', '44100', '48000'];
                if (!in_array(IPS_GetProperty($this->ReadPropertyInteger('PollyID'), 'SampleRate'), $sampleRateAllowList)) {
                    return 205;
                }
            }
            $output = $this->ReadPropertyInteger('OutputInstance');
            if ($output != 0) {
                return 104;
            }
            if (!IPS_InstanceExists(($output))) {
                return 202;
            }
            switch ($this->ReadPropertyInteger('OutputType')) {
                case self::DS_SONOS:
                    if (IPS_GetInstance($output)['ModuleInfo']['ModuleID'] != '{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}') {
                        return 203;
                    }
                    break;

                case self::DS_MEDIA:
                    if (IPS_GetInstance($output)['ModuleInfo']['ModuleID'] != '{2999EBBB-5D36-407E-A52B-E9142A45F19C}') {
                        return 203;
                    }
                    break;
            }

            return 102;
        };

        $this->SetStatus($getInstanceStatus());
    }

    private function getInstanceOptions($guid)
    {
        $instances = IPS_GetInstanceListByModuleID($guid);
        $caption = $this->Translate('None');
        if ($guid == '{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}' && !(IPS_LibraryExists('{B9D841BF-12F4-4BC6-B89C-0F6CB538865B}'))) {
            $caption = $this->Translate('Sonos Module not installed');
        }
        $options[] = [
            'value'   => 0,
            'caption' => $caption
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
