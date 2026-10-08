document.addEventListener('DOMContentLoaded', () => {
    const source=document.querySelector('[name=source_language]');
    source?.addEventListener('change',()=>{const checkbox=[...document.querySelectorAll('[name="enabled_languages[]"]')].find(el=>el.value===source.value);if(checkbox)checkbox.checked=true;});
    const placement=document.getElementById('vmm-placement');
    function placementFields(){
        const floating=document.getElementById('vmm-floating_position'),sidebar=document.getElementById('vmm-sidebar');
        if(floating)floating.closest('tr').hidden=placement?.value!=='floating';
        if(sidebar)sidebar.closest('tr').hidden=placement?.value!=='sidebar';
    }
    placement?.addEventListener('change',placementFields);placementFields();
    const mode = document.getElementById('vmm-url_mode'), panel = document.getElementById('vmm-subdomain-help'), language = document.getElementById('vmm-dns-language');
    let host = document.getElementById('vmm-en_host');
    if (!mode || !host || !panel) return;
    const siteBase = new URL(panel.dataset.home);
    const planner = document.getElementById('vmm-hosting-planner');
    const publicUrl = document.getElementById('vmm-public-url'), publicEn = document.getElementById('vmm-public-en'), root = document.getElementById('vmm-hosting-root');
    if (!/^(localhost|127\.|\[?::1)/.test(siteBase.hostname) && !siteBase.hostname.endsWith('.localhost')) { publicUrl.value = siteBase.href; publicEn.value = host.value.trim(); }
    function update() {
        panel.hidden = mode.value !== 'subdomain';
        planner.hidden = panel.hidden;
        if (panel.hidden) return;
        const code=language?.selectedOptions[0]?.dataset.code ?? 'en';
        let base = siteBase;
        try { if (publicUrl.value.trim()) base = new URL(publicUrl.value.trim()); } catch { panel.textContent = 'Bitte eine vollständige öffentliche Adresse mit https:// eingeben.'; return; }
        if (!['http:', 'https:'].includes(base.protocol) || base.username || base.password) { panel.textContent = 'Bitte eine gültige Website-Adresse eingeben.'; return; }
        if (!host.value.trim()) host.value = code + '.' + siteBase.hostname.replace(/^www\./, '') + (siteBase.port ? ':' + siteBase.port : '');
        panel.replaceChildren();
        let target;
        const targetHost = publicUrl.value.trim() ? (publicEn.value.trim() || code + '.' + base.hostname.replace(/^www\./, '')) : host.value.trim();
        if (!/^[a-z0-9.-]+(?::\d+)?$/i.test(targetHost)) { panel.textContent = 'Bitte einen gültigen Sprachhostnamen ohne Pfad eingeben.'; return; }
        try { target = new URL(base.protocol + '//' + targetHost); } catch { panel.textContent = 'Bitte einen gültigen Sprachhostnamen eingeben.'; return; }
        if (target.hostname === base.hostname) { panel.textContent = 'Bitte einen anderen Hostnamen als die Basisadresse verwenden.'; return; }
        const local = base.hostname === 'localhost' || base.hostname.endsWith('.localhost') || /^(127\.|\[?::1)/.test(base.hostname);
        const heading = document.createElement('h3'); heading.textContent = 'Einrichtung für ' + target.host; panel.append(heading);
        function row(label, value, explanation) {
            const block = document.createElement('p'), title = document.createElement('strong'), text = document.createElement('textarea'), button = document.createElement('button'), status = document.createElement('span');
            title.textContent = label; text.value = value; text.readOnly = true; text.rows = value.split('\n').length; text.className = 'large-text code'; text.setAttribute('aria-label', label);
            button.type = 'button'; button.className = 'button'; button.textContent = 'Kopieren'; button.setAttribute('aria-label', label + ' kopieren'); status.setAttribute('role', 'status');
            button.addEventListener('click', async () => { try { await navigator.clipboard.writeText(value); status.textContent = ' Kopiert'; } catch { text.select(); status.textContent = document.execCommand('copy') ? ' Kopiert' : ' Bitte den markierten Text kopieren'; } });
            block.append(title, text, button, status); if (explanation) { const note = document.createElement('small'); note.textContent = explanation; block.append(document.createElement('br'), note); } panel.append(block);
        }
        row('English-Adresse', target.origin + base.pathname, 'Diese Adresse führt auf dieselbe WordPress-Installation.');
        if (local) {
            row('Windows hosts-Eintrag', '127.0.0.1 ' + target.hostname, 'Falls der Name nicht automatisch aufgelöst wird: als Administrator in C:\\Windows\\System32\\drivers\\etc\\hosts ergänzen. Ports gehören nicht in die hosts-Datei. Kein öffentlicher DNS-Eintrag erforderlich.');
            row('Lokaler Testaufruf', 'curl.exe -I --resolve ' + target.hostname + ':' + (target.port || (target.protocol === 'https:' ? '443' : '80')) + ':127.0.0.1 ' + target.origin + base.pathname, 'Der bestehende lokale Docker-Webserver nimmt beide Hostnamen auf demselben Port an.');
        } else {
            const zone = base.hostname.replace(/^www\./, '');
            const relative = target.hostname.endsWith('.' + zone) ? target.hostname.slice(0, -(zone.length + 1)) : target.hostname;
            row('DNS – Typ', 'CNAME', 'Eine mögliche DNS-Variante. Erst prüfen: Beim Anlegen der Subdomain setzt der Hoster häufig bereits passende A-/AAAA-Einträge. Dann sind keine zusätzlichen DNS-Einträge nötig.');
            row('DNS – Name / Host', relative, 'Für die DNS-Zone ' + zone + '. Falls das Panel vollständige Namen verlangt: ' + target.hostname + '. Bei einer anderen DNS-Zone den relativen Namen entsprechend anpassen.');
            row('DNS – Ziel / Wert', base.hostname + '.', 'Zeigt auf denselben Webhost wie die Hauptseite. Keine URL, kein https:// und kein Verzeichnispfad. Manche Panels ergänzen den abschließenden Punkt selbst.');
            row('DNS – TTL', '3600', '3600 Sekunden / 1 Stunde, sofern das Panel einen Wert verlangt; ansonsten Standard übernehmen.');
            row('DNS – CNAME-Eintrag', target.hostname + '. IN CNAME ' + base.hostname + '.', 'Im DNS-Panel Typ CNAME wählen; Name: ' + target.hostname + '; Ziel: ' + base.hostname + '. Nicht zusätzlich einen A-/AAAA-Eintrag für denselben Namen anlegen. TTL kann auf Standard bleiben.');
            row('Hosting – Subdomain / Domainname', target.hostname, 'Als zusätzliche Domain/Subdomain derselben Installation anlegen. Keine HTTP-Weiterleitung auf die Basisdomain einrichten.');
            if (root.value.trim()) row('Hosting – Zielverzeichnis', root.value.trim(), 'Genau dasselbe Verzeichnis wie bei der bestehenden WordPress-Domain auswählen.');
            else { const p = document.createElement('p'); p.textContent = 'Zielverzeichnis: die bestehende Domain-Zuordnung im Hosting-Panel öffnen und deren Verzeichnis unverändert für die Subdomain übernehmen. Den tatsächlichen Panel-Pfad kann VMM nicht zuverlässig ermitteln.'; panel.append(p); }
            const instructions = document.createElement('p');
            instructions.textContent = 'Beim Anbieter: Subdomain anlegen → bestehendes WordPress-Verzeichnis zuweisen → DNS prüfen → SSL aktivieren. Werden DNS extern verwaltet, die Einträge dort vornehmen. Für diese Sprach-Subdomain ist kein Wechsel der Nameserver erforderlich.';
            panel.append(instructions);
            const extra = document.createElement('p'); extra.textContent = 'Falls der Anbieter A statt CNAME verlangt: die Webspace-IPv4 aus dem Hosting-Panel als Ziel verwenden; AAAA nur mit der vom Anbieter angegebenen IPv6. VMM erfindet keine Server-IP. Bei bestehenden DNS-Einträgen zuerst Konflikte prüfen; ein CNAME darf nicht neben A/AAAA für denselben Namen stehen.'; panel.append(extra);
            row('Apache – bestehender VirtualHost', 'ServerAlias ' + target.hostname, 'In den bestehenden VirtualHost dieser WordPress-Installation ergänzen, anschließend Konfiguration prüfen und neu laden.');
            row('Nginx – bestehender server-Block', 'server_name ' + base.hostname + ' ' + target.hostname + ';', 'Die server_name-Zeile im bestehenden WordPress-server-Block ergänzen; weitere vorhandene Namen beibehalten. Apache und Nginx sind Alternativen.');
        }
        if (target.protocol === 'https:') row('HTTPS-Zertifikat – zusätzlicher DNS-Name', target.hostname, 'Beim Hosting ein Zertifikat für diesen Namen ausstellen/erweitern und dem gleichen VirtualHost zuordnen.');
        const note = document.createElement('p'); note.textContent = 'Die Angaben werden aus der oben angegebenen Website-Adresse erzeugt. Bei einem Hosting-Panel die Subdomain dem bestehenden WordPress-Verzeichnis zuweisen. Die öffentliche Vorschau wird nicht als aktive Sprach-URL gespeichert; auf dem Live-System den Sprachhostnamen oben setzen. VMM verändert DNS oder Serverkonfiguration nicht automatisch.'; panel.append(note);
    }
    mode.addEventListener('change', update); document.querySelectorAll('.vmm-language-host').forEach(input=>input.addEventListener('input',update));
    language?.addEventListener('change',()=>{host=document.getElementById(language.value);publicEn.value='';update();});
    [publicUrl, publicEn, root].forEach(input => input.addEventListener('input', update)); update();
});

