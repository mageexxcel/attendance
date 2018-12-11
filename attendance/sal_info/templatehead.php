<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<?php
/***********************************/
/*****without header and footer*****/
/***********************************/
    if( $include_header_footer == false ) {
    ?>
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml" lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
        <title>Header and Footer example</title>
        <link rel="stylesheet" type="text/css" href="proxima-nova-web-fonts-master/fonts/fonts.min.css" />
        <style type="text/css">
            @page {
                margin: 1cm 2cm 2cm;

            }
            body {
                font-family: 'Proxima Nova', Georgia, sans-serif;
                margin:55px 0px 10px;
                font-family: 'Proxima Nova', Georgia, sans-serif;
            }
            .title {
                font-size:28px;
                font-weight:800;
                margin:0px;
            }
        </style>
    </head>
    <body>
       #page_content
    </body>
</html>

<?php
} 
else {
    /***********************************/
    /*****with header and footer*****/
    /***********************************/
    ?>

<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml" lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
        <title>Header and Footer example</title>
        <style>
            @page {
                size:210mm 297mm;
                margin: 0px;
            }
            p { text-align: justify; }
        </style>
    </head>
    <body>
        <table style="width:100%;position:fixed;top:0px;margin-top:-3px;">
            <tbody>
                <tr>
                    <td>
                        <div style="height: 22px;background-color: #5f9baa; margin-left: -10px;width: 105%;"></div>                        
                    </td>
                </tr>
                <tr>
                    <td align="right"><img src="Excelogo-black.jpg" style="height:45px;margin-top: 30px;padding-right: 40px;"></td>
                </tr>
            </tbody>
        </table>

        <table style="width:100%;position:fixed;bottom:100px;font-family: 'proxima-nova', sans-serif;font-size: 0.8em;">
            <tbody>
                <tr>
                    <td style="padding-left: 100px; padding-right: 100px;">
                        <div style="margin-bottom: 20px;border-top: 0.05em solid #888888;padding-top:5px;padding-left: 15px;">
                            Excellence Technosoft Pvt. Ltd.<br/>
                            CIN No. U72200DL2010PTC205087 <br/>
                            Corp. Office: C-86 B, 2nd Floor, Sector 8, Noida. U.P 201301<br/>
                            Reg. Office: 328 Gagan Vihar IST Mezzazzine, New Delhi 110051<br/>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div style="height: 5px;background-color: #5f9baa;margin-left: -10px;width: 105%;"></div>
                    </td>
                </tr>
            </tbody>
        </table>   
        
        <table style="margin-top: 140px;margin-bottom:50px;width: 100%;padding-left: 100px;padding-right: 100px;">
            <tbody>
                <tr>
                    <td>
                        #page_content
                    </td>
                </tr>
            </tbody>
        </table>
    </body>
</html>
<?php
}
?>




