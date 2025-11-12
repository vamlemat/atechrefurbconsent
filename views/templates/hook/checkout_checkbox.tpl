
<div id="arc-consent" class="card" style="margin-bottom:1rem;">
  <div class="card-block">
    <label style="display:flex;align-items:flex-start;gap:.5rem;">
      <input type="checkbox" id="arc_checkbox" {if $arc_checked}checked{/if}>
      <span><strong>{l s='CONDICIÓN OBLIGATORIA' mod='atechrefurbconsent'}:</strong> {$arc_text nofilter}</span>
    </label>
  </div>
</div>
