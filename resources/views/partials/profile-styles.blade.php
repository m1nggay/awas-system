{{-- My Profile styles, shared by the consumer and the admin / meter reader profile pages. --}}
<style>
  .pf{--pf-border:#e3edf3;--pf-muted:#6b7f8c;}
  .pf-card{background:#fff;border:1px solid var(--pf-border);border-radius:14px;box-shadow:0 1px 3px rgba(16,40,60,.05);padding:22px 24px;}
  .pf-title{display:flex;align-items:center;gap:8px;font-weight:700;font-size:15.5px;margin:0 0 16px;color:var(--text-dark);}
  .pf-title i{color:var(--primary);font-size:19px;}
  .pf-label{font-size:12px;color:var(--pf-muted);margin-bottom:2px;}
  .pf-value{font-weight:600;font-size:14.5px;color:var(--text-dark);word-break:break-word;}
  .pf-pill{display:inline-block;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;background:#e3f6ec;color:#1a7d4b;}
  .pf-pill.off{background:#eef1f3;color:#6b7f8c;}

  /* Banner */
  .pf-banner{display:flex;align-items:center;gap:22px;margin-bottom:16px;}
  .pf-avatar{position:relative;flex:none;width:110px;height:110px;}
  .pf-avatar .pic{width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid #fff;box-shadow:0 0 0 3px #cfe6f4;background:#e8f3f8;display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;color:var(--primary-dark);}
  .pf-cam{position:absolute;right:2px;bottom:4px;width:34px;height:34px;border-radius:50%;border:3px solid #fff;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;padding:0;}
  .pf-cam:hover{background:var(--primary-dark);}
  .pf-photo-menu{min-width:220px;}
  .pf-photo-menu .hint{font-size:11.5px;color:var(--pf-muted);padding:2px 16px 6px;}
  .pf-who{flex:1;min-width:0;}
  .pf-name{font-size:22px;font-weight:700;margin:0;line-height:1.25;}
  .pf-edit-link{border:0;background:none;padding:0;color:var(--primary);font-size:13px;font-weight:500;}
  .pf-edit-link:hover{text-decoration:underline;}
  .pf-edit-link:disabled{color:var(--pf-muted);text-decoration:none;}
  .pf-name-form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:4px;}
  .pf-summary{display:flex;flex:none;}
  .pf-summary > div{padding:0 22px;border-left:1px solid var(--pf-border);}
  .pf-summary > div:first-child{border-left:0;}

  /* Columns */
  .pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:stretch;}
  .pf-col{display:flex;flex-direction:column;gap:16px;}
  .pf-col > .pf-card:last-child{flex:1;}
  .pf-info{display:grid;grid-template-columns:1fr 1fr;gap:18px 24px;}
  .pf-info .wide{grid-column:1 / -1;}
  .pf .form-label{font-size:12.5px;font-weight:600;color:var(--text-dark);margin-bottom:5px;}
  .pf-code{max-width:200px;font-size:20px;letter-spacing:6px;text-align:center;}
  .pf-link-btn{display:inline-flex;align-items:center;gap:6px;}

  @media (max-width:899.98px){
    .pf-banner{flex-wrap:wrap;}
    .pf-summary{flex-basis:100%;flex-wrap:wrap;gap:10px 0;}
    .pf-summary > div:first-child{padding-left:0;}
    .pf-grid{grid-template-columns:1fr;}
  }
  @media (max-width:520px){
    .pf-card{padding:18px;}
    .pf-banner{gap:16px;}
    .pf-info{grid-template-columns:1fr;}
  }
</style>
