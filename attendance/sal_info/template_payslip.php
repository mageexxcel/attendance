<style>
    body{
        // font-family: "sans-serif";
        // height:120%;
    }
    .tab1, .tab1 th, .tab1 td {
        //  border: 1px solid black;
        border-collapse: collapse;
        padding:2px 10px;
        min-height: 50px;

    } 
    img {
        height:70px;
        width:240px;
    }
    p {
        //  line-height: 0em;
        font-size: 29px;
        font-weight:800;
        padding-top:10px;
        margin-bottom: 12px;
        margin-top: -20px;
    }
    .span_bold {
        font-weight: 600;
        font-size: 16px;
    }
    .tab3 td{
        font-size:12px;
         font-family: "sans-serif";
    }

</style>
<body>
    <table class="tab1"  style=" width:750px; margin:0px;">
        <tbody>
            <tr>
                <td width="370px;"><p>Excellence Technologies</p>
                    <span class="span_bold">Salary Statement For the Month of <?php echo $month_name . ",&nbsp;" . $data['year']; ?></span></td>
                <td align="right"><img src="Excelogo-black.jpg" style="width:200px; height:52px;"></td>
            </tr>
            <tr>
                <td colspan="2" align="center"><hr></td>
            </tr>

        </tbody>
    </table>
    <table class="tab1"  style=" width:750px; margin:0px;">
        <tbody>

            <tr>
                <td>
                    Employee Name
                </td>
                <td>
<?php echo $data['name'] ?>
                </td>
                <td>
                </td>
                <td>
                </td>
            </tr>
            <tr>
                <td>
                    Designation
                </td>
                <td>
<?php echo $data['designation'] ?>
                </td>
                <td>
                    Joining Date
                </td>
                <td>
<?php echo date("F d, Y", strtotime($data['joining_date'])) ?>
                </td>
            </tr>
            <tr>
                <td>
                    Total Working Days
                </td>
                <td>
<?php echo $data['total_working_days'] ?>
                </td>
                <td>
                    Days Present
                </td>
                <td>
<?php echo $data['days_present'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Paid Leave Taken
                </td>
                <td>
<?php echo $data['paid_leaves'] ?>
                </td>
                <td>
                    Leave Without Pay
                </td>
                <td>
<?php echo $data['unpaid_leaves'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Total Leave Taken
                </td>
                <td>
<?php echo $data['total_leave_taken'] ?>
                </td>
                <td>
                    Leave Accumulated
                </td>
                <td>
<?php echo $data['allocated_leaves'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Previous Leave Balance
                </td>
                <td>
<?php echo $data['leave_balance'] ?>
                </td>
                <td>
                    Final Leave Balance
                </td>
                <td>
<?php echo $data['final_leave_balance'] ?>
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                    <br>
                    <span class="span_bold">Earnings</span>
                    <br><br>
                </td>

                <td colspan="2" align="center">
                    <br>
                    <span class="span_bold">Deductions</span>
                    <br><br>
                </td>
            </tr>

            <tr>
                <td>

                </td>
                <td>
                    Amount(In Rs)
                </td>
                <td>

                </td>
                <td>
                    Amount(In Rs)
                </td>
            </tr>
            <tr>
                <td>
                    Basic
                </td>
                <td>
<?php echo $data['basic'] ?>
                </td>
                <td>
                    EPF
                </td>
                <td>
<?php echo $data['epf'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    HRA
                </td>
                <td>
<?php echo $data['hra'] ?>
                </td>
                <td>
                    Loan
                </td>
                <td>
<?php echo $data['loan'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Conveyance
                </td>
                <td>
<?php echo $data['conveyance'] ?>
                </td>
                <td>
                    Advance
                </td>
                <td>
<?php echo $data['advance'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Medical Allowance
                </td>
                <td>
<?php echo $data['medical_allowance'] ?>
                </td>
                <td>
                    Holding Amount
                </td>
                <td>
<?php echo $data['misc_deduction'] ?>
                </td>
            </tr>
            <tr>
                <?php if($data['loyalty_bonus'] != ""){ ?>
                   <td>
                    Loyalty Bonus
                </td>
                <td>
<?php echo $data['loyalty_bonus'] ?>
                </td>
            <?php   } else { ?>
                <td>
                    Special Allowance
                </td>
                <td>
<?php echo $data['special_allowance'] ?>
                </td>
            <?php } ?>   
                <td>
                    TDS
                </td>
                <td>
<?php echo $data['tds'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Arrears
                </td>
                <td>
<?php echo $data['arrear'] ?>
                </td>
                <td>
                   Misc Deduction
                </td>
                <td>
<?php echo $data['misc_deduction_2'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    Bonus
                </td>
                <td>
<?php echo $data['bonus'] ?>
                </td>
                <td>

                </td>
                <td>

                </td>
            </tr>
            <tr>
                <td>
                    Total Earnings
                </td>
                <td>
<?php echo $data['total_earning'] ?>
                </td>
                <td>
                    Total Deductions
                </td>
                <td>
<?php echo $data['total_deduction'] ?>
                </td>
            </tr>
            <tr>
                <td>
                    
                    <span class="span_bold">Net Salary(Rs)</span>
                </td>
                <td>
                    <span class="span_bold"><?php echo $data['net_salary'] ?></span>
                </td>
                <td>

                </td>
                <td>

                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <hr>
                </td>

            </tr>
            <tr>
                <td colspan="2">
                    <span class="span_bold">For Excellence Technosoft Pvt Ltd</span><br>
                    <span class="span_bold">Manish Prakash <br>(Director)</span>
                </td>
                <td colspan="2" align="right">
                    <span class="span_bold" >PAN No: AACCE4460B</span><br>
                    <span class="span_bold" >TAN No: DELE05598F</span>
                </td>
            </tr>
        </tbody>
    </table>
    <table class = "tab3" style=" width:750px;position:absolute;bottom:50px;border-top:3px sold">
        <tbody>
            <tr>
                <td colspan="2">
                    <div  style="background-color: #622423;height:5px;"></div>
                    <div  style="background-color: #622423;height:1px;margin-top:2px;"></div>

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
