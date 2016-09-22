<?php 
require_once 'header.php';
?>
    
    <table class="tab2"  style=" width:650px; margin:0px;">
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
<?php 
require_once 'footer.php';
?>

