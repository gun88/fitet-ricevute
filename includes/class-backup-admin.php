<?php
defined('ABSPATH') || exit;
final class FR_Backup_Admin {
    public function init(): void {
        add_action('fitet_ricevute_cleanup_backup',[$this,'cleanup'],10,3);
        add_action('admin_menu',fn()=>add_submenu_page('fitet-ricevute','Importa / esporta dati','Importa / esporta dati','manage_options','fitet-ricevute-backup',[$this,'page']));
        add_action('admin_post_fitet_ricevute_export',[$this,'download']);
        add_action('admin_post_fitet_ricevute_preview_import',[$this,'preview']);
        add_action('admin_post_fitet_ricevute_restore',[$this,'restore']);
    }
    private function guard(): void {if(!current_user_can('manage_options'))wp_die('Permessi insufficienti.','',['response'=>403]);}
    private function error(Throwable $error): void {wp_die(esc_html($error instanceof RuntimeException?$error->getMessage():'Operazione non riuscita.'),'Backup dati',['response'=>400,'back_link'=>true]);}
    public function cleanup(string $token,string $path,string $sha): void {
        $directory=realpath(get_temp_dir());$file=realpath($path);
        if($directory&&$file&&str_starts_with(strtolower(wp_normalize_path($file)),rtrim(strtolower(wp_normalize_path($directory)),'/').'/')&&str_starts_with(basename($file),'fitet-backup')&&is_file($file)&&hash_file('sha256',$file)===$sha)unlink($file);
        if(preg_match('/^[a-f0-9]{32}$/D',$token))delete_transient('fr_backup_preview_'.$token);
    }
    public function download(): void {
        $this->guard();check_admin_referer('fitet_ricevute_export');
        try{$data=FR_Backup::export();FR_Backup::validate($data);$bytes=FR_Backup::encode($data);}catch(Throwable $e){$this->error($e);return;}
        nocache_headers();header('Content-Type: application/gzip');header('Content-Disposition: attachment; filename="fitet-ricevute-backup-'.gmdate('Ymd-His').'.json.gz"');header('X-Content-Type-Options: nosniff');echo $bytes;exit;
    }
    private function staged(string $token): array {
        if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new RuntimeException('Anteprima non valida.');$stage=get_transient('fr_backup_preview_'.$token);
        if(!is_array($stage)||$stage['user']!==get_current_user_id()||!is_file($stage['path']))throw new RuntimeException('Anteprima scaduta. Carica nuovamente il backup.');
        if(hash_file('sha256',$stage['path'])!==$stage['sha'])throw new RuntimeException('File di anteprima modificato.');return $stage;
    }
    public function preview(): void {
        $this->guard();check_admin_referer('fitet_ricevute_preview_import');
        try{
            $file=$_FILES['backup_file']??[];
            if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new RuntimeException('Carica un backup .json.gz entro il limite upload del server.');
            if(($file['size']??0)>16*1024*1024)throw new RuntimeException('Il backup supera 16 MB compressi.');
            $bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new RuntimeException('Backup non leggibile.');$data=FR_Backup::decode($bytes);
            $token=bin2hex(random_bytes(16));$path=wp_tempnam('fitet-backup');if(!$path||file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Impossibile preparare l’anteprima.');
            $saved=set_transient('fr_backup_preview_'.$token,['user'=>get_current_user_id(),'path'=>$path,'sha'=>hash('sha256',$bytes),'summary'=>FR_Backup::summary($data)],15*MINUTE_IN_SECONDS);
            if(!$saved){unlink($path);throw new RuntimeException('Anteprima non salvata.');}
            wp_schedule_single_event(time()+15*MINUTE_IN_SECONDS,'fitet_ricevute_cleanup_backup',[$token,$path,hash('sha256',$bytes)]);
        }catch(Throwable $e){$this->error($e);return;}
        wp_safe_redirect(admin_url('admin.php?page=fitet-ricevute-backup&preview='.$token));exit;
    }
    public function restore(): void {
        $this->guard();check_admin_referer('fitet_ricevute_restore');
        try{
            if(empty($_POST['confirm_replace']))throw new RuntimeException('Conferma la sostituzione dei dati del plugin.');
            $token=sanitize_text_field(wp_unslash($_POST['preview_token']??''));$stage=$this->staged($token);$bytes=file_get_contents($stage['path']);if($bytes===false)throw new RuntimeException('Backup non leggibile.');
            FR_Backup::restore(FR_Backup::decode($bytes));unlink($stage['path']);delete_transient('fr_backup_preview_'.$token);
        }catch(Throwable $e){$this->error($e);return;}
        wp_safe_redirect(admin_url('admin.php?page=fitet-ricevute-backup&restored=1'));exit;
    }
    public function page(): void {
        $this->guard();$stage=null;
        if(isset($_GET['preview']))try{$stage=$this->staged(sanitize_text_field(wp_unslash($_GET['preview'])));}catch(Throwable $e){echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>';}
        ?><div class="wrap fr-admin"><h1>Importa / esporta dati FITET Ricevute</h1>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=fitet-ricevute')); ?>">Torna alla configurazione</a></p>
        <?php if(isset($_GET['restored'])): ?><div class="notice notice-success"><p>Backup ripristinato. Gli utenti del portale devono effettuare nuovamente l’accesso.</p></div><?php endif; ?>
        <h2>Esporta backup completo</h2><p>Include tornei, gare, iscrizioni, prezzi, stati dei rimborsi, bozze, emissioni, impostazioni, firma, timbro e PDF originali disponibili. Password, sessioni e credenziali extranet sono escluse.</p>
        <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fitet_ricevute_export'),'fitet_ricevute_export')); ?>">Esporta backup completo (.json.gz)</a>
        <h2>Importa backup</h2><p>Il ripristino sostituisce i dati del plugin, conservando password e credenziali del sito di destinazione. Gli utenti sono associati per username; gli account mancanti vengono creati disabilitati, da riattivare dopo aver impostato una password. I dati WordPress estranei al plugin non vengono modificati.</p>
        <p>Formato .json.gz; massimo 16 MB compressi e 64 MB non compressi. Limite upload attuale del server: <?php echo esc_html(size_format(min(16*1024*1024,wp_max_upload_size()))); ?>. Non usare il portale durante il ripristino.</p>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fitet_ricevute_preview_import"><?php wp_nonce_field('fitet_ricevute_preview_import'); ?>
        <p><label for="fr-backup-file">File di backup</label> <input type="file" id="fr-backup-file" name="backup_file" accept=".gz,.json.gz,application/gzip" required></p><?php submit_button('Verifica e mostra anteprima'); ?></form>
        <?php if($stage): ?><h2>Anteprima importazione</h2><ul><?php foreach($stage['summary'] as $name=>$count): ?><li><?php echo esc_html((['gare'=>'Gare','iscrizioni'=>'Iscrizioni','prezzi'=>'Prezzi','rimborsi'=>'Stati rimborsi','carrello'=>'Righe bozza rimborso','emissioni'=>'Rimborsi emessi','pdf'=>'PDF originali','pdf_mancanti'=>'PDF non disponibili'][$name]??$name).': '.$count); ?></li><?php endforeach; ?></ul>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fitet_ricevute_restore"><input type="hidden" name="preview_token" value="<?php echo esc_attr($_GET['preview']); ?>"><?php wp_nonce_field('fitet_ricevute_restore'); ?>
        <label><input type="checkbox" name="confirm_replace" value="1" required> Confermo la sostituzione dei dati FITET Ricevute con quelli del backup</label><?php submit_button('Ripristina backup','primary'); ?></form><?php endif; ?>
        </div><?php
    }
}
