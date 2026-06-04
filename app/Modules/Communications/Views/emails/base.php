<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{SUBJECT}}</title>
<style>
  /* Reset */
  body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
  table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
  img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
  body{height:100% !important;margin:0 !important;padding:0 !important;width:100% !important;background-color:#f4f6f8}
  /* Main */
  .wrapper{width:100%;table-layout:fixed;background-color:#f4f6f8;padding:32px 0}
  .main{background-color:#ffffff;margin:0 auto;max-width:600px;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
  .header{background-color:#1773C8;padding:24px 32px}
  .header h1{color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:600;margin:0}
  .body{padding:32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#202223}
  .body h1,.body h2,.body h3{color:#202223;margin-top:0}
  .body a{color:#1773C8;text-decoration:underline}
  .body p{margin:0 0 16px 0}
  .body ul,.body ol{margin:0 0 16px 0;padding-left:24px}
  .footer{background-color:#f4f6f8;padding:20px 32px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb}
  @media only screen and (max-width:600px){
    .wrapper{padding:16px 0}
    .header,.body,.footer{padding:20px}
  }
</style>
</head>
<body>
<div class="wrapper">
  <table class="main" role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td class="header">
        <h1>{{SUBJECT}}</h1>
      </td>
    </tr>
    <tr>
      <td class="body">
        {{BODY}}
      </td>
    </tr>
    <tr>
      <td class="footer">
        Comunicado Interno Trantor Technologies.
      </td>
    </tr>
  </table>
</div>
</body>
</html>
