{{-- Styles for the Online Payment panel (resident._pay-panel), on the Pay Bill page and the Current Bills pop-up. --}}
<style>
  .pay-dialog{max-width:560px;margin:0 auto;padding:0;}
  .modal .pay-dialog{max-width:none;margin:0;border:0;box-shadow:none;}
  /* In the pop-up: the header stays put and the rest scrolls when it is taller than the screen. */
  .modal-dialog-scrollable .pay-dialog{display:flex;flex-direction:column;flex:1 1 auto;min-height:0;max-height:100%;}
  .modal-dialog-scrollable .pay-head{flex:none;}
  .modal-dialog-scrollable .pay-body{overflow-y:auto;min-height:0;}
  /* Thin, light scrollbar that blends with the white panel (same style as the sidebar's). */
  @supports not selector(::-webkit-scrollbar){
    .pay-body{scrollbar-width:thin;scrollbar-color:rgba(15,45,70,0.18) transparent;}
  }
  .pay-body::-webkit-scrollbar{width:6px;}
  .pay-body::-webkit-scrollbar-track{background:transparent;}
  .pay-body::-webkit-scrollbar-button{display:none;}
  .pay-body::-webkit-scrollbar-thumb{background:rgba(15,45,70,0.18);border-radius:10px;}
  .pay-body::-webkit-scrollbar-thumb:hover{background:rgba(15,45,70,0.35);}
  .pay-dialog .pay-head{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);}
  .pay-dialog .pay-head h3{margin:0;font-size:16px;font-weight:700;}
  .pay-close{font-size:22px;line-height:1;color:var(--text-muted);text-decoration:none;padding:2px 6px;border-radius:8px;background:none;border:0;}
  .pay-close:hover{background:#f1f5f8;color:var(--text-dark);}
  .pay-body{padding:18px 20px 20px;}
  .pay-amount-label{font-size:12px;color:var(--text-muted);}
  .pay-amount{font-size:30px;font-weight:700;color:var(--primary-dark);line-height:1.15;}
  .due-chip{background:#fff3df;color:#9a5b00;border-radius:999px;padding:4px 12px;font-size:12px;font-weight:600;white-space:nowrap;}
  .bill-details-frame{width:100%;height:60vh;border:1px solid var(--border);border-radius:10px;display:block;margin-top:10px;}
  .pay-q{font-weight:600;font-size:14px;margin:18px 0 10px;}
  .method-list{display:flex;flex-direction:column;gap:10px;}
  .method-item{border:1.5px solid var(--border);border-radius:10px;background:#fff;overflow:hidden;}
  .method-item:has(.method-tab[aria-expanded="true"]){border-color:var(--primary);box-shadow:0 0 0 1px var(--primary) inset;}
  .method-tab{display:flex;justify-content:space-between;align-items:center;width:100%;border:0;background:none;padding:13px 16px;cursor:pointer;text-align:left;}
  .method-tab strong{font-size:14.5px;}
  .method-tab[aria-expanded="true"]{background:#f0f7fb;}
  .method-arrow{font-size:14px;color:var(--text-muted);transition:transform .2s;}
  .method-tab[aria-expanded="true"] .method-arrow{transform:rotate(180deg);color:var(--primary);}
  .method-panel{border-top:1px solid var(--border);padding:16px;}
  .wallet-choices{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .wallet-btn{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 12px;border:2px solid var(--border);border-radius:12px;background:#fff;font-weight:700;font-size:16px;color:#1f2d3d;transition:border-color .15s,background .15s,transform .15s;}
  .wallet-btn:hover:not(:disabled){border-color:var(--wallet);background:#fafdff;transform:translateY(-1px);}
  .wallet-btn .dot{flex:none;width:36px;height:36px;border-radius:50%;background:var(--wallet);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;}
  .wallet-btn:disabled{opacity:.6;}
  /* Only one e-wallet (GCash): one wide button in its color, centered with its note. */
  .wallet-choices.single{grid-template-columns:1fr;max-width:380px;margin:4px auto 0;}
  .wallet-btn.solid{width:100%;min-height:56px;background:var(--wallet);border-color:var(--wallet);color:#fff;font-size:17px;box-shadow:0 4px 12px rgba(0,125,254,.25);}
  .wallet-btn.solid:hover:not(:disabled){background:var(--wallet);filter:brightness(.94);}
  .wallet-btn.solid .dot{background:#fff;color:var(--wallet);width:32px;height:32px;}
  .wallet-note{text-align:center;max-width:380px;margin-left:auto;margin-right:auto;}
  .qr-row{display:grid;grid-template-columns:130px 1fr;gap:16px;align-items:start;}
  .qr-frame{border:1px solid var(--border);border-radius:10px;padding:6px;background:#fff;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;}
  .qr-frame img{width:100%;height:100%;object-fit:contain;image-rendering:pixelated;}
  .qr-steps{padding-left:18px;margin:8px 0 0;font-size:13px;}
  /* PayMongo QR: big enough to scan from another phone */
  [data-qrph] .qr-row{grid-template-columns:1fr;gap:10px;}
  [data-qrph] .qr-row > div:first-child{max-width:240px;width:100%;margin:0 auto;}
  [data-qrph] .qr-wait{font-size:13.5px;}
  .qr-steps li{margin-bottom:3px;}
  .upload-box{background:#f4fafc;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:14px;}
  .upload-box .form-label{font-size:12.5px;font-weight:600;margin-bottom:4px;}
  @media (max-width:520px){
    .qr-row{grid-template-columns:1fr;}
    .qr-frame{max-width:220px;margin:0 auto;}
  }
</style>
