<?php
if (!defined('DOKU_INC')) die();

class action_plugin_mediaautoscale extends DokuWiki_Action_Plugin {
    const MAX_EDGE=1440;
    const MAX_BYTES=2097152;
    const MAX_PIXELS=64000000;
    const JPEG_QUALITY=90;
    const MIN_EDGE=320;

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('MEDIA_UPLOAD_FINISH','BEFORE',$this,'handle');
    }

    public function handle(Doku_Event $event,$param) {
        if (!is_array($event->data) || count($event->data)<6) return;
        $src=$event->data[0];
        $id=$event->data[2];
        $ext=strtolower(pathinfo($id,PATHINFO_EXTENSION));
        if (!in_array($ext,array('jpg','jpeg','png','gif','ico'),true)) return;

        $workDir=rtrim(trim($this->getConf('workdir')),'/');
        $convert=trim($this->getConf('convert'));
        if ($workDir==='' || $convert==='') return $this->fail($event,'Image autoscale: plugin is not configured. Set workdir and convert before enabling it.');
        if (!is_dir($workDir) || !is_writable($workDir)) return $this->fail($event,'Image autoscale: configured work directory is not writable.');
        if (!is_file($convert) || !is_executable($convert)) return $this->fail($event,'Image autoscale: configured ImageMagick executable is not executable.');
        if (!is_file($src)) return $this->fail($event,'Image autoscale: temporary file is missing.');

        $size=@filesize($src);
        if ($size===false) return $this->fail($event,'Image autoscale: cannot determine file size.');
        if ($ext==='gif' || $ext==='ico') {
            if ($size>self::MAX_BYTES) $this->fail($event,'Image autoscale: GIF/ICO larger than 2 MiB is not supported.');
            return;
        }

        $info=@getimagesize($src);
        if ($info===false || empty($info[0]) || empty($info[1])) return $this->fail($event,'Image autoscale: invalid image.');
        $w=(int)$info[0]; $h=(int)$info[1]; $type=(int)$info[2];
        $jpeg=($ext==='jpg' || $ext==='jpeg');
        if (($jpeg && $type!==IMAGETYPE_JPEG) || (!$jpeg && $type!==IMAGETYPE_PNG)) return $this->fail($event,'Image autoscale: file content does not match extension.');
        if ((float)$w*(float)$h>self::MAX_PIXELS) return $this->fail($event,'Image autoscale: image exceeds 64 MP.');
        $long=max($w,$h);
        if ($long<=self::MAX_EDGE && $size<=self::MAX_BYTES) return;

        $edge=min(self::MAX_EDGE,$long);
        while (true) {
            $out=tempnam($workDir,'mediaautoscale-');
            if ($out===false) return $this->fail($event,'Image autoscale: cannot create work file.');
            if (!$this->convertImage($src,$out,$edge,$jpeg,$workDir,$convert)) {
                @unlink($out);
                return $this->fail($event,'Image autoscale: ImageMagick conversion failed.');
            }
            clearstatcache(true,$out);
            $outSize=@filesize($out);
            $outInfo=@getimagesize($out);
            $expected=$jpeg ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
            if ($outSize===false || $outInfo===false || (int)$outInfo[2]!==$expected || max((int)$outInfo[0],(int)$outInfo[1])>self::MAX_EDGE) {
                @unlink($out);
                return $this->fail($event,'Image autoscale: converted image failed validation.');
            }
            if ($outSize<=self::MAX_BYTES) {
                $event->data[0]=$out;
                $event->data[5]='rename';
                return;
            }
            @unlink($out);
            if ($edge<=self::MIN_EDGE) break;
            $edge=max(self::MIN_EDGE,(int)floor($edge*0.85));
        }
        $this->fail($event,'Image autoscale: cannot reduce image below 2 MiB at quality policy.');
    }

    private function convertImage($src,$out,$edge,$jpeg,$workDir,$convert) {
        $cmd='MAGICK_TMPDIR='.escapeshellarg($workDir).' TMPDIR='.escapeshellarg($workDir).' '.
            escapeshellarg($convert).' -limit memory 64MiB -limit map 128MiB -limit disk 512MiB -limit thread 1 ';
        if ($jpeg) $cmd.='-define '.escapeshellarg('jpeg:size='.(2*$edge).'x'.(2*$edge)).' ';
        $cmd.=escapeshellarg($src).' ';
        if ($jpeg) $cmd.='-auto-orient ';
        $cmd.='-strip -resize '.escapeshellarg($edge.'x'.$edge.'>').' ';
        if ($jpeg) $cmd.='-quality '.self::JPEG_QUALITY.' ';
        $cmd.=escapeshellarg(($jpeg?'jpeg:':'png:').$out).' 2>&1';
        $output=array(); $status=1;
        exec($cmd,$output,$status);
        return $status===0 && is_file($out) && filesize($out)>0;
    }

    private function fail(Doku_Event $event,$message) {
        $event->result=array($message,-1);
        $event->preventDefault();
        $event->stopPropagation();
    }
}
