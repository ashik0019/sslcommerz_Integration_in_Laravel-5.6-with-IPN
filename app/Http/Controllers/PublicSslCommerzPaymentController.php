<?php
namespace App\Http\Controllers;
use App\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Session;
use Illuminate\Routing\UrlGenerator;
use App\Http\Controllers;
session_start();

class PublicSslCommerzPaymentController extends Controller
{

    public function index(Request $request) 
    {

            # Here you have to receive all the order data to initate  payment.
            # Lets your oder trnsaction informations are saving in a table called "orders"
            # In orders table order uniq identity is "order_id","order_status" field contain status of the transaction, "grand_total" is the order amount to be paid and "currency" is for storing Site Currency which will be checked with paid currency.

            $post_data = array();
            $post_data['total_amount'] = '1000'; # You cant not pay less than 10
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = uniqid(); // tran_id must be unique
            $order = new Order();
            $order->grand_total = $post_data['total_amount'];
            $order->currency = $post_data['currency'];
            $order->order_id = $post_data['tran_id'];
            $order->order_status = 'Pending';
            $order->phone = '01916050960';
            $order->email = Auth::user()->email;
            $order->name = Auth::user()->name;
            $order->save();

            #Start to save these value  in session to pick in success page.
            $_SESSION['payment_values']['tran_id']=$post_data['tran_id'];
            #End to save these value  in session to pick in success page.

            $server_name=$request->root()."/";
            $post_data['success_url'] = $server_name . "success";
            $post_data['fail_url'] = $server_name . "fail";
            $post_data['cancel_url'] = $server_name . "cancel";

            
            #Before  going to initiate the payment order status need to update as Pending.
            $update_product = DB::table('orders')
                                    ->where('order_id', $post_data['tran_id'])
                                    ->update(['order_status' => 'Pending','currency' => $post_data['currency']]);

            $sslc = new SSLCommerz();
            # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
            $payment_options = $sslc->initiate($post_data, false);

            if (!is_array($payment_options)) {
                print_r($payment_options);
                $payment_options = array();
            }

    }

    public function success(Request $request) 
    {
        $check = json_encode($_POST);
       dd($_POST);
        echo "Transaction is Successful";

        $data = [
            'order_status'=>'completed',

        ];

        $sslc = new SSLCommerz();
        #Start to received these value from session. which was saved in index function.
        $tran_id = $_SESSION['payment_values']['tran_id'];
        #End to received these value from session. which was saved in index function.

        #Check order status in order tabel against the transaction id or order id.
        $order_detials = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->select('order_id', 'order_status','currency','grand_total')->first();

        if($order_detials->order_status=='Pending')
        {
            $validation = $sslc->orderValidate($tran_id, $order_detials->grand_total, $order_detials->currency, $request->all());
            if($validation == TRUE) 
            {
                /*
                That means IPN did not work or IPN URL was not set in your merchant panel. Here you need to update order status
                in order table as Processing or Complete.
                Here you can also sent sms or email for successfull transaction to customer
                */ 
                $update_product = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->update(['order_status' => 'Complete']);

                echo "<br >Transaction is successfully Complete";
            }
            else
            {
                /*
                That means IPN did not work or IPN URL was not set in your merchant panel and Transation validation failed.
                Here you need to update order status as Failed in order table.
                */ 
                $update_product = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->update(['order_status' => 'Failed']);
                echo "validation Fail";
            }    
        }
        else if($order_detials->order_status=='Processing' || $order_detials->order_status=='Complete')
        {
            /*
             That means through IPN Order status already updated. Now you can just show the customer that transaction is completed. No need to udate database.
             */
            echo "Transaction is successfully Complete";
        }
        else
        {
             #That means something wrong happened. You can redirect customer to your product page.
            echo "Invalid Transaction";
        }    
         


    }
    public function fail(Request $request) 
    {
         $tran_id = $_SESSION['payment_values']['tran_id'];
         $order_detials = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->select('order_id', 'order_status','currency','grand_total')->first();

        if($order_detials->order_status=='Pending')
        {
            dd($_POST);
            $update_product = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->update(['order_status' => 'Failed']);
            echo "Transaction is Failed";
        }
         else if($order_detials->order_status=='Processing' || $order_detials->order_status=='Complete')
        {
            echo "Transaction is already Successful";
        }  
        else
        {
            echo "Transaction is Invalid"; 
        }        
                            
    }

     public function cancel(Request $request) 
    {
        $tran_id = $_SESSION['payment_values']['tran_id'];

        $order_detials = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->select('order_id', 'order_status','currency','grand_total')->first();

        if($order_detials->order_status=='Pending')
        {
            $update_product = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->update(['order_status' => 'Canceled']);
            echo "Transaction is Cancel";                
        }
         else if($order_detials->order_status=='Processing' || $order_detials->order_status=='Complete')
        {
            echo "Transaction is already Successful";
        }  
        else
        {
            echo "Transaction is Invalid"; 
        }                 

        
    }
     public function ipn(Request $request)
    {
        #Received all the payement information from the gateway
      if($request->input('tran_id')) #Check transation id is posted or not.
      {

          $tran_id = $request->input('tran_id');

        #Check order status in order tabel against the transaction id or order id.
         $order_details = DB::table('orders')
                            ->where('order_id', $tran_id)
                            ->select('order_id', 'order_status','currency','grand_total')->first();

                if($order_details->order_status =='Pending')
                {
                    $sslc = new SSLCommerz();
                    $validation = $sslc->orderValidate($tran_id, $order_details->grand_total, $order_details->currency, $request->all());
                    if($validation == TRUE)
                    {
                        /*
                        That means IPN worked. Here you need to update order status
                        in order table as Processing or Complete.
                        Here you can also sent sms or email for successfull transaction to customer
                        */
                        $update_product = DB::table('orders')
                                    ->where('order_id', $tran_id)
                                    ->update(['order_status' => 'Complete']);

                        echo "Transaction is successfully Complete";
                    }
                    else
                    {
                        /*
                        That means IPN worked, but Transation validation failed.
                        Here you need to update order status as Failed in order table.
                        */
                        $update_product = DB::table('orders')
                                    ->where('order_id', $tran_id)
                                    ->update(['order_status' => 'Failed']);

                        echo "validation Fail";
                    }

                }
                else if($order_details->order_status == 'Processing' || $order_details->order_status =='Complete')
                {

                  #That means Order status already updated. No need to udate database.

                    echo "Transaction is already successfully Complete";
                }
                else
                {
                   #That means something wrong happened. You can redirect customer to your product page.

                    echo "Invalid Transaction";
                }
        }
        else
        {
            echo "Inavalid Data";
        }
    }

}
