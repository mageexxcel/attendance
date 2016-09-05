<style>
    body{
        font-family: "sans-serif";
        // height:120%;
    }
    .tab1, .tab1 th, .tab1 td {
        border: 1px solid black;
        border-collapse: collapse;
        padding:2px 10px;
        min-height: 50px;

    } 
    p{
        line-height: 0.5em;
    }
    .tab1 th{
        height:30px;
        vertical-align: top;
    }
    .tab2 {
        padding-left:5px;
    }

    .space td{
        height:50px;
    }
    .tab3 td{
        font-size:12px;
    }
    input[type="checkbox"]{
        width:20px;
        height:20px;
    }
</style>
<body>
    <table class="tab2"  style=" width:650px; margin:0px;">
        <tbody>
            <tr>
                <td colspan="2" align="right"><img src="logo.png"></td>
            </tr>
            <tr>
                <td colspan="2" align="center"><p style="font-size:35px;padding:20px 0px">Invoice</p></td>
            </tr>
            <tr>
                <td>
                    <span style="font-size:16px; font-weight:800;text-decoration: underline">Bill To</span><br><br>
                    ##client_name##<br><br>
                    ##client_address##
                </td>
                <td align= "right">
                    Due Date: ##due_date##<br>
                    Invoice Number: ##invoice_no##<br>
                    Excellence Technosoft Pvt Ltd<br>
                    PAN No: AACCE4460B<br>
                    CST: AACCE4460BSD001</p>
                </td>
            </tr>
        </tbody>
    </table>
    <br><br>
    <table class="tab1"  style=" width:655px; margin:0px;">
        <tbody>
            <tr style="background-color: #7EABD6">
                <th>Qty</th>
                <th>Item (Description)</th>
                <th>Unit Price</th>
                <th>Total</th>

            </tr>

            <?php
            $i = 1;
            foreach ($arr_item as $val) {

                echo "<tr class='space'>";
                echo "<td>" . $i . "</td>";
                echo "<td style='width:50%'>" . $val['item_description'] . "</td>";
                echo "<td>" . $val['item_unit_price'] . "&nbsp;##currency##</td>";
                echo "<td>" . $val['item_total_price'] . "&nbsp;##currency##</td>";
                echo "</tr>";
                $i++;
            }
            ?>

            <tr>
                <td colspan="3" align="right">Sub Total</td>
                <td>##sub_total##&nbsp;##currency##</td>
            </tr>
            <tr>
                <td colspan="3" align="right">Service Tax</td>
                <td>##service_tax##</td>
            </tr>
            <tr>
                <td colspan="3" align="right">Total Amount</td>
                <td>##total_amount## &nbsp;##currency##</td>
            </tr>

            </tr>
        </tbody>
    </table>
    <table class = "tab3" style=" width:655px;position:absolute;bottom:50px;border-top:3px sold">
        <tbody>
            <tr>
                <td colspan="2" style="background-color: #622423;height:5px;">

                </td>
            </tr>
            <tr>
                <td>
                    <b>Excellence Technosoft Pvt Ltd</b>
                </td>
                <td style="text-align: right">
                    <a href="http://www.excellencetechnologies.in">http://www.excellencetechnologies.in</a> 
                </td>
            </tr>
            <tr>
                <td>
                    <b>Corp Office:</b> C84-A, Sector 8, Noida, U.P. - 201301
                </td>
                <td style="text-align: right">
                    <b>CIN:</b> U72200DL2010PTC205087
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <b>Regd Office:</b> 328 GAGAN VIHAR IST MEZZAZINE,NEW DELHI-110051
                </td>
            </tr>

        </tbody>
    </table>
</body>

