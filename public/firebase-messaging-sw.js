importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyC43ImjqEzCySvNWzXl0mzrod9xISxFg6Q",
    authDomain: "tenr-dd273.firebaseapp.com",
    projectId: "tenr-dd273",
    storageBucket: "tenr-dd273.firebasestorage.app",
    messagingSenderId: "834186365132",
    appId: "1:834186365132:web:6440a3bbaf4c4c0f409a69",
    measurementId: "G-4WME36PH2X"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || ''
    });
});