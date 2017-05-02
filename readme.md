CityPay Paylink Magento Plugin
====

CityPay Paylink Magento Plugin is a plugin that supplements Magento community edition 
with support for a form leading to payment processing using CityPay hosted payment forms.

![Magento Logo](magento.png)

## Installation Guide 

* Open the Magento Administration Panel.
* Select the System menu, then the Magento Connect submenu, and then the Magento Connect Manager sub-submenu.
* Following selection of the Magento Connect Manager, the browser may be referred to a login form as follows.
* Log in to Magento Connect Manager, using the administrator user name and password combination.
* Following login, the main Magento Connect Manager form is displayed which enables the Merchant to install a new extension, either through the Magento Connect service or by direct upload of a package file to the server. The Paylink 3 Magento extension is available and may be installed using the Magento Connect service.
* Clicking on the "Magento Connect" link appearing at point 1 of the "Install New Extensions" section of the Magento Connect Manager leads to the Magento Connect service appearing in a new browser window or tab. The Paylink 3 Magento extension may be located by entering a search for "CityPay Paylink" in the search text box on the top right hand side of the page as it appears in the browser.
* Following submission of the search query, the Magento Connect service will display a list of results which ought to show the Paylink 3 Magento extension at or near the top of the search results.
* Upon selection of the Paylink 3 Magento extension, a Magento Connect page specific to the extension is displayed which shows a button which says "Install Now".
* Upon clicking on the Install Now button, if you are not already registered with or logged in to the Magento Connect service, you will be prompted to register or login.
* Upon selecting the login link, Magento Connect will display a modal dialog box requiring you to enter the registered user name and password.
* Once you are logged in to the Magento Connect service, Magento Connect will provide you with a button which says "Get Extension Key", the purpose of which is to generate a URL that allows the extension to be downloaded from Magento Connect or CityPay. Before generating an extension key, Magento Connect requires you to accept, on behalf of the Merchant, the licence agreement for the extension. The Paylink 3 Magento extension is licensed under the Open Software License.
* After you have signalled agreement to the extension licence a Get Extension Key is displayed which, upon clicking, causes Magento Connect to generate a URL that may be selected and copied from the Magento Connect page to the clipboard, ready to be pasted into the Magento Connect Manager. The Magento Connect Manager is located in the original browser window or tab used to commence the installation process.
* After selecting the original browser window or tab containing Magento Connect Manager, the URL selected and copied in the previous step should be pasted into the text box provided from the clipboard and the Install button clicked.
* Following the click on the Install button, Magento will proceed to download the extension and provide feedback relating to the package dependencies, the package version and the status which should show "Ready to install". Immediately following the package status, you are invited to cancel the installation by clicking the Cancel Installation button; or to proceed with installation by clicking the Proceed button.
* Upon clicking the Proceed button, Magento will run the automated package installation process to generate the results in a window appearing at the bottom of the Magento Connect Manager page.
* Following installation, you may click the Refresh button to ensure the extension has been successfully installed after which time you can return to the Magento Admin Panel by returning to the top of the Magento Connect Manager page and clicking the "Return to Admin" link on the top right hand side. On returning to the Magento Admin Panel, the extension may be configured by selecting the "Configuration" menu option from the "System" menu.
* Following selection of the "Configuration" menu option from the "System" menu, the Configuration page is displayed.
* To amend the payment method configuration for the Magento instance, you will need to scroll down the page to find the subsection appearing in the sidebar on the left hand side of the page entitled "Sales" which contains a subsection item entitled "Payment Methods".
* On selection of the Payment Methods subsection item referenced above, the Payment Methods configuration form is displayed which provides, in the context of one of a number of collapsible forms, a form entitled "CityPay Paylink". You may have to click on the expand icon () on the right hand side of the title bar to see the CityPay Paylink configuration form.
* To enable CityPay Paylink, first you must select "Yes" from the drop-down menu labeled "Enabled" as shown below.
* Second, you must complete the section appearing on the form entitled "Account settings" and, in particular, completing the text boxes labeled "Merchant ID" and "Licence Key" which relate to the Merchant ID and Licence Key provided to the Merchant by CityPay on opening a live or test account.
* The Payment Methods configuration may be saved by clicking the "Save Config" button near the top right hand side of the Payment Methods configuration page.
