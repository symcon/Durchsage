<?php

declare(strict_types=1);

include_once __DIR__ . '/libs/WebHookModule.php';

class Durchsage extends WebHookModule
{
    const DS_SONOS = 0;
    const DS_MEDIA = 1;
    //Sonos only supports following sample rates: https://support.sonos.com/s/article/79?language=de
    const DS_SONOS_SAMPLE_RATE = ['', '16000', '22050', '24000', '32000', '44100', '48000'];

    const GUID_SONOS = '{52F6586D-A1C7-AAC6-309B-E12A70F6EEF6}';
    const GUID_MEDIA = '{2999EBBB-5D36-407E-A52B-E9142A45F19C}';
    const GUID_POLLY = '{6EFA02E1-360F-4120-B3DE-31EFCDAF0BAF}';
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID, 'durchsage/' . $InstanceID);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Get details for available networks
        $networks = Sys_GetNetworkInfo();

        //Properties
        $this->RegisterPropertyInteger('PollyID', 0);
        $this->RegisterPropertyInteger('OutputType', self::DS_SONOS);
        $this->RegisterPropertyInteger('OutputInstance', 0);
        $this->RegisterPropertyString('SymconIP', (count($networks) > 0) ? $networks[0]['IP'] : '');
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
        $currentStatus = $this->GetStatus();
        if ($currentStatus != 102) {
            foreach (json_decode($this->GetConfigurationForm(), true)['status'] as $status) {
                if ($status['code'] == $currentStatus) {
                    echo $this->Translate($status['caption']);
                    return;
                }
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

    public function UpdateOutput(int $OutputType)
    {
        switch ($OutputType) {
            case self::DS_SONOS:
                //Hide Media Player elements
                $this->UpdateFormField('MediaPlayerVolume', 'visible', false);

                //Update output select
                $this->UpdateFormField('OutputInstance', 'caption', $this->Translate('Sonos Player'));
                $this->UpdateFormField('OutputInstance', 'moduleID', self::GUID_SONOS);
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
                $this->UpdateFormField('OutputInstance', 'moduleID', self::GUID_MEDIA);
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
            'type'     => 'SelectModule',
            'name'     => 'PollyID',
            'caption'  => 'Text-to-Speech Instance (Polly)',
            'moduleID' => self::GUID_POLLY
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
            'type'     => 'SelectModule',
            'name'     => 'OutputInstance',
            'caption'  => $this->ReadPropertyInteger('OutputType') === self::DS_MEDIA ? 'Media Player' : 'Sonos Player',
            'moduleID' => $this->ReadPropertyInteger('OutputType') === self::DS_MEDIA ? self::GUID_MEDIA : self::GUID_SONOS
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
            if (IPS_GetInstance($polly)['ModuleInfo']['ModuleID'] != self::GUID_POLLY) {
                return 201;
            }
            if (IPS_GetProperty($this->ReadPropertyInteger('PollyID'), 'OutputFormat') != 'mp3') {
                return 204;
            }
            if ($this->ReadPropertyInteger('OutputType') === self::DS_SONOS) {
                if (!in_array(IPS_GetProperty($this->ReadPropertyInteger('PollyID'), 'SampleRate'), self::DS_SONOS_SAMPLE_RATE)) {
                    return 205;
                }
            }
            $output = $this->ReadPropertyInteger('OutputInstance');
            if ($output === 0) {
                return 104;
            }
            if (!IPS_InstanceExists(($output))) {
                return 202;
            }
            switch ($this->ReadPropertyInteger('OutputType')) {
                case self::DS_SONOS:
                    if (IPS_GetInstance($output)['ModuleInfo']['ModuleID'] != self::GUID_SONOS) {
                        return 203;
                    }
                    break;

                case self::DS_MEDIA:
                    if (IPS_GetInstance($output)['ModuleInfo']['ModuleID'] != self::GUID_MEDIA) {
                        return 203;
                    }
                    break;
            }

            return 102;
        };

        $this->SetStatus($getInstanceStatus());
    }
}
