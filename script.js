function sendNotification() {
    OneSignal.sendNotification({
       contents: {en: "New Message"},
       include_player_ids: ["64caa31b-fb53-43e8-ad2d-bb66cda3b586"]
    }, function(error, result) {
       if (error) {
         console.log("Error sending notification: ", error);
       } else {
         console.log("Notification sent successfully: ", result);
       }
    });
   }
